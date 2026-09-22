#!/usr/bin/env bash
#
# setup-server.sh – Frischen EU-Produktionsserver (Ubuntu 24.04 LTS / Debian 12)
# für das b2e-template-Deployment vorbereiten (idempotent, komplett non-interaktiv).
#
# Installiert: Docker Engine + Docker Compose-Plugin (offizielle Docker-Repos),
# git, ca-certificates/curl und legt den ausführenden Benutzer in die docker-Gruppe.
#
# Verwendung:
#   sudo ./scripts/setup-server.sh            # als root/Sudo ausführen
#
# Wirkt nur ein, wenn Docker/Compose noch fehlen – erneutes Ausführen ist safe.

set -euo pipefail

# ---------------------------------------------------------------------------
# Umgebungsprüfung
# ---------------------------------------------------------------------------
if [[ "${EUID}" -ne 0 ]]; then
    echo "Fehler: Bitte mit sudo root-Rechten ausführen." >&2
    exit 1
fi

command -v curl >/dev/null 2>&1 || {
    echo "curl fehlt – wird installiert."
    export DEBIAN_FRONTEND=noninteractive
    apt-get update -qq
    apt-get install -y -qq curl ca-certificates
}

# ---------------------------------------------------------------------------
# Docker vorhanden?
# ---------------------------------------------------------------------------
if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
    echo "Docker + Compose bereits installiert: $(docker --version), $(docker compose version | head -n1)"
else
    echo "Docker Engine + Compose-Plugin werden über das offizielle Docker-Repository installiert …"

    . /etc/os-release
    if [[ "${ID}" != "ubuntu" && "${ID}" != "debian" ]]; then
        echo "Fehler: Nur Ubuntu/Debian werden unterstützt (erkannt: ${ID}) (${ID_LIKE:-})." >&2
        exit 1
    fi

    # Abhängigkeiten für apt-via-https
    export DEBIAN_FRONTEND=noninteractive
    apt-get update -qq
    apt-get install -y -qq \
        ca-certificates curl gnupg

    install -m 0755 -d /etc/apt/keyrings
    curl -fsSL "https://download.docker.com/linux/${ID}/gpg" \
        | gpg --dearmor --yes -o /etc/apt/keyrings/docker.gpg
    chmod a+r /etc/apt/keyrings/docker.gpg

    # Ubuntu nutzt 24.04 -> "noble"; Debian 12 -> "bookworm"; ID_LIKE enthält nichts für Ubuntu.
    CODENAME="${VERSION_CODENAME:-${UBUNTU_CODENAME:-}}"
    if [[ -z "${CODENAME}" ]]; then
        echo "Fehler: Kein Distro-Codename ermittelbar." >&2
        exit 1
    fi

    echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/${ID} ${CODENAME} stable" \
        > "/etc/apt/sources.list.d/docker.list"

    apt-get update -qq
    apt-get install -y -qq \
        docker-ce \
        docker-ce-cli \
        containerd.io \
        docker-buildx-plugin \
        docker-compose-plugin
fi

# ---------------------------------------------------------------------------
# Daemon sichern + starten
# ---------------------------------------------------------------------------
systemctl enable --now docker >/dev/null 2>&1 || true
docker info >/dev/null 2>&1 || {
    echo "Fehler: Docker-Daemon läuft nicht – bitte systemctl status docker prüfen." >&2
    exit 1
}

# Ausführenden Benutzer (SUDO_USER, falls vorhanden) in die docker-Gruppe aufnehmen
TARGET_USER="${SUDO_USER:-}"
if [[ -n "${TARGET_USER}" && "${TARGET_USER}" != "root" ]]; then
    usermod -aG docker "${TARGET_USER}"
    echo "Benutzer '${TARGET_USER}' wurde in die docker-Gruppe aufgenommen."
    echo "Hinweis: Nach dem nächsten Login greift die Gruppe ohne sudo."
fi

# git (für den Klon des Repos) sicherstellen
command -v git >/dev/null 2>&1 || {
    export DEBIAN_FRONTEND=noninteractive
    apt-get install -y -qq git
}

cat <<'EOF'

--------------------------------------------------------------
setup-server.sh abgeschlossen.
Nächste Schritte (siehe docs/deploy.md):
  1. DNS: A-Record der Domain auf diese Server-IP zeigen lassen.
  2. Repo klonen, z. B.:  git clone <repo> /opt/b2e-template && cd /opt/b2e-template
  3. .env anlegen (Vorlage: Abschnitt "Umgebungsvariablen" in docs/deploy.md).
  4. Deployment starten:  ./scripts/deploy.sh
--------------------------------------------------------------
EOF