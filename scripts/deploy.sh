#!/usr/bin/env bash
#
# deploy.sh – b2e-template auf einem vorbereiteten Server bauen, starten und
# veröffentlichen. Idempotent: ein erneuter Lauf spielt nur die Änderungen ein.
#
# Ablauf:  .env-Prüfung (change-me-Guard) -> Bild bauen -> Stack starten
#          -> Migrationen + optimize im App-Container ausführen.
#
# Verwendung (im Repo-Root des Servers):
#   ./scripts/deploy.sh                 # Standard (build + up + migrate + optimize)
#   B2E_SKIP_BUILD=1 ./scripts/deploy.sh # nur up + migrate (Bild existiert bereits)
#
# Voraussetzungen: Docker + Compose-Plugin, .env mit Produktionswerten.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT}"

COMPOSE=(docker compose -f compose.prod.yaml)
IMAGE_LABEL="b2e-template/app:prod"
ENV_FILE="${ROOT}/.env"

# ---------------------------------------------------------------------------
# .env vorhanden?
# ---------------------------------------------------------------------------
if [[ ! -f "${ENV_FILE}" ]]; then
    cat >&2 <<'EOF'
Fehler: Keine .env gefunden.
Lege sie auf Basis der Vorlage in docs/deploy.md an (Abschnitt "Umgebungsvariablen")
und fülle alle Werte (u. a. APP_KEY, DB-Passwörter, APP_DOMAIN, ACME_EMAIL) aus.
EOF
    exit 1
fi

# ---------------------------------------------------------------------------
# Env-Guard: Pflichtwerte vorhanden und kein Platzhalter mehr drin?
# ---------------------------------------------------------------------------
fail=""
env_value() { grep -E "^${1}=" "${ENV_FILE}" | head -n1 | cut -d= -f2- | xargs; }

check_not_placeholder() {
    local key="$1"
    local val
    val="$(env_value "${key}")"
    if [[ -z "${val}" ]]; then
        fail="${fail}\n  - ${key} ist leer"
    elif [[ "${val}" == "change-me" || "${val}" == "CHANGE-ME" ]]; then
        fail="${fail}\n  - ${key} enthält noch den Platzhalter 'change-me'"
    fi
}

check_app_key() {
    local val
    val="$(env_value APP_KEY)"
    if [[ "${val}" != base64:* ]]; then
        fail="${fail}\n  - APP_KEY fehlt oder ist keine base64-Enkodierung"
    fi
}

check_app_key
check_not_placeholder APP_DOMAIN
check_not_placeholder ACME_EMAIL
check_not_placeholder DB_PASSWORD
check_not_placeholder MYSQL_PASSWORD
check_not_placeholder MYSQL_ROOT_PASSWORD
check_not_placeholder MYSQL_DATABASE
check_not_placeholder MYSQL_USER

# ENTRA nur prüfen, wenn aktiviert ist
if [[ "$(env_value ENTRA_ENABLED)" == "true" ]]; then
    check_not_placeholder ENTRA_TENANT_ID
    check_not_placeholder ENTRA_CLIENT_ID
    check_not_placeholder ENTRA_CLIENT_SECRET
fi

if [[ -n "${fail}" ]]; then
    echo -e "Fehler: Ungültige Werte in .env:${fail}" >&2
    exit 1
fi

echo "Env-Guard: .env ist vollständig und enthält keine Platzhalter."

# ---------------------------------------------------------------------------
# Bild bauen
# ---------------------------------------------------------------------------
if [[ "${B2E_SKIP_BUILD:-0}" != "1" ]]; then
    echo "== Bild bauen: ${IMAGE_LABEL} =="
    "${COMPOSE[@]}" build --pull
fi

# ---------------------------------------------------------------------------
# Stack starten (wartet auf Healthchecks: --wait)
# ---------------------------------------------------------------------------
echo "== Stack starten =="
# --wait steht ab Compose v2.24 zur Verfügung; sonst klassisch starten.
if ! "${COMPOSE[@]}" up -d --wait; then
    echo "Hinweis: 'up -d --wait' nicht unterstützt – starte ohne Wartephase."
    "${COMPOSE[@]}" up -d
fi

# ---------------------------------------------------------------------------
# Post-Deploy im App-Container: Migrationen + optimierte Caches
# ---------------------------------------------------------------------------
echo "== Migrationen ausführen =="
"${COMPOSE[@]}" exec -T app php artisan migrate --force

echo "== Optimize (config/route/view-Caches + opcache-ready) =="
"${COMPOSE[@]}" exec -T app sh -c 'php artisan optimize:clear >/dev/null 2>&1 || true; php artisan optimize'

echo "== Status =="
"${COMPOSE[@]}" ps

APP_DOMAIN="$(env_value APP_DOMAIN)"
echo
echo "Deployment abgeschlossen. Die Anwendung ist erreichbar unter:"
echo "  https://${APP_DOMAIN:-<APP_DOMAIN aus .env>}"
echo "Erster Login via Entra-ID-SSO (role 'nutzer' o. ä.) oder lokaler Fortify-Login."