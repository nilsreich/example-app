#!/usr/bin/env bash
#
# backup.sh – Logische DB-Sicherung (mysqldump aus dem mysql-Container) plus
# Tar-Sicherung des app-storage-Volumes (u. a. Screenshots). Rotation: standard-
# mäßig die letzten 7 Sicherungen beider Arten.
#
# Verwendung:
#   ./scripts/backup.sh                       # Standard: /var/backups/b2e-template, KEEP=7
#   BACKUP_DIR=/mnt/backup KEEP=14 ./scripts/backup.sh
#
# Für GoBD/DSGVO-Audits: Backup-Ziel außerhalb des Servers (NFS, Objektspeicher)
# via BACKUP_DIR einbinden; Cron:  0 2 * * * /opt/b2e-template/scripts/backup.sh

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT}"

BACKUP_DIR="${BACKUP_DIR:-/var/backups/b2e-template}"
KEEP="${KEEP:-7}"
PROJECT_NAME="b2e-template"
TS="$(date +%Y%m%d-%H%M%S)"

COMPOSE=(docker compose -f compose.prod.yaml)
DB_FILE="${BACKUP_DIR}/database-${TS}.sql.gz"
STORAGE_FILE="${BACKUP_DIR}/storage-${TS}.tar.gz"

mkdir -p "${BACKUP_DIR}"

# ---------------------------------------------------------------------------
# 1) Logische Datenbank-Sicherung (mysqldump aus dem mysql-Container)
# ---------------------------------------------------------------------------
echo "== DB-Dump (mysqldump) =="
# Credentials werden aus der Container-ENV gelesen (MYSQL_ROOT_PASSWORD) –
# sie stehen nicht im Skript.
"${COMPOSE[@]}" exec -T mysql sh -c \
    'exec mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines --triggers "$MYSQL_DATABASE"' \
    | gzip > "${DB_FILE}"
echo "  → ${DB_FILE} ($(du -h "${DB_FILE}" | cut -f1))"

# ---------------------------------------------------------------------------
# 2) Dateisicherung des app-storage-Volumes (Screenshots/Uploads)
# ---------------------------------------------------------------------------
echo "== app-storage-Volume (Tar) =="
docker run --rm \
    -v "${PROJECT_NAME}_app-storage:/data:ro" \
    -v "${BACKUP_DIR}:/backup" \
    alpine:3 tar czf "/backup/storage-${TS}.tar.gz" -C /data .
echo "  → ${STORAGE_FILE} ($(du -h "${STORAGE_FILE}" | cut -f1))"

# ---------------------------------------------------------------------------
# 3) Rotation: nur die jüngsten $KEEP Sicherungen jeder Art behalten
# ---------------------------------------------------------------------------
rotate() {
    local pattern="$1"
    # Abbruch, wenn BACKUP_DIR unerwartet aussieht (Robustheit gegen rm -f)
    if [[ "${BACKUP_DIR}" != /* || "${BACKUP_DIR}" == "/" || "${BACKUP_DIR}" == "/var/backups" ]]; then
        echo "Warnung: Rotation für '${pattern}' übersprungen (ungeprüftes BACKUP_DIR=${BACKUP_DIR})." >&2
        return 0
    fi
    local to_delete
    to_delete="$(ls -1t "${BACKUP_DIR}"/${pattern} 2>/dev/null | tail -n +$((KEEP + 1)) || true)"
    if [[ -n "${to_delete}" ]]; then
        echo "== Rotation (${pattern}, KEEP=${KEEP}) =="
        echo "${to_delete}" | while read -r old; do
            rm -f "${old}"
            echo "  gelöscht: ${old}"
        done
    fi
}

rotate "database-*.sql.gz"
rotate "storage-*.tar.gz"

# ---------------------------------------------------------------------------
# 4) Zusammenfassung
# ---------------------------------------------------------------------------
echo
echo "Sicherung abgeschlossen. Aktueller Inhalt von ${BACKUP_DIR}:"
ls -1t "${BACKUP_DIR}"/database-*.sql.gz "${BACKUP_DIR}"/storage-*.tar.gz 2>/dev/null | head -n $((KEEP * 2))