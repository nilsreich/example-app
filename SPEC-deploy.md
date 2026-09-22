# Spec: deploy

Modul-Id: `deploy` (Capability Map: B2E-App-Template). Freigabestatus: **freigegeben (2026-09-22)**.

## Objective

Produktions-Docker-Kit für EU-Hosting (DSGVO/GoBD-Kontext, „echte Kundendaten"), das aus einem frischen EU-Server in wenigen Befehlen eine laufende Instanz macht.

- **Ein-Container-Satz** via `compose.prod.yaml`: `app` (php-fpm), `caddy` (Reverse-Proxy, auto-HTTPS per Let's Encrypt), `mysql:8.4`, `redis`. Persistente Volumes für DB, privaten Storage (Screenshots!), Logs; Healthchecks; kein Root im App-Container.
- **Dockerfile**: Multi-Stage (node-build für Vite-Assets → composer-Install → php-fpm-Runtime), reproduzierbar, aktuelle PHP-Version (^8.3).
- **Konfiguration per Env**: `.env.production.example` mit allen Variablen (`APP_LOCALE=de`, DB, Redis, Mail, Storage-S3-kompatibel/EU optional, Caddy-Domains, Entra-Credentials, AI-Provider); keine Secrets im Repo.
- **Ein-Befehl-Deploy**: `scripts/deploy.sh` (Klon/Git-Pull der Instanz-Konfiguration, `docker compose up -d --build`, Migrationen, bzw. Start-Gateway) — Ziel: „Server frisch → Instanz läuft unter HTTPS".
- **CI**: bestehender `.github/workflows/tests.yml` bleibt; erweitert um Container-Build-/Smoke-Check (optional Image-Publish). Sail (Dev-`compose.yaml`) wird vom Produktions-Kit getrennt und bleibt Dev-only.
- EU-Datenresidenz explizit dokumentiert (Regionenwahl, kein US-only-Cloud-Zwang; S3/Storage optional EU).

## Tech Stack

- Docker Compose v2, Caddy 2 (auto-HTTPS), PHP 8.3-fpm, MySQL 8.4, Redis 7
- CI: GitHub Actions (bestehend)

## Commands

```
cp .env.production.example .env && $EDITOR .env
./scripts/deploy.sh                 # baut + startet Prod-Kit, führt Migrationen aus
docker compose -f compose.prod.yaml ps / logs -f app
composer test                       # lokaler Qualitäts-Gate (unverändert)
```

## Project Structure

```
Dockerfile.prod                     # Multi-Stage (node → composer → php-fpm)
compose.prod.yaml                   # app, caddy, mysql, redis (+ Volumes/Healthchecks)
Caddyfile.prod                      # Routing, Assets, Livewire/WebSockets, Sicherheitsheader
.env.production.example             # alle Produktionsvariablen, dokumentiert (de)
scripts/deploy.sh                   # Ein-Befehl-Deploy (Server: git pull, compose up, migrate)
scripts/setup-server.sh             # Basis-Provisioning (Docker, Firewall, Docker-Traefik-frei; Caddy handled TLS)
docs/deploy.md                      # Anleitung EU-Hosting, Regionen, Backup, Rotation
.github/workflows/tests.yml         # erweitert um Build-/Smoke-Check
```

## Code Style

Shell-Skripte: `set -euo pipefail`, idempotent, keine interaktiven Prompts (CI/Headless-tauglich). Env-Dateien dokumentiert, Sensibles nur als `env(...)`-Verweis.

## Testing Strategy

- CI: after code tests zusätzlich `docker compose -f compose.prod.yaml config` (Validierung) und Build des Images (Pull/Fresh install).
- Lokal: vollständiger Prod-Smoke (build + up auf lokalem Docker, HTTPS-Hint über caddy ab Caddyfile).
- e2e: Playwright gegen die Prod-Compose-Instanz (Smoke) — opt-in, nicht im Standard-CI.

## Boundaries

- **Always:** Secrets nur via ENV; `compose.prod.yaml` validierbar; Deploy-Skripte idempotent + headless.
- **Ask first:** zusätzliche Dienste (Worker/Queue, Objektspeicher-Provider, Mail-Provider), Zertifikats-/Proxy-Wechsel, Server-Automation (Ansible statt setup.sh).
- **Never:** Secrets/`.env`/Keys im VCS; Root-Prozesse im App-Container (ohne dokumentierte Ausnahme); US-Only-Services mit echten Kundendaten (DSGVO/GoBD).

## Success Criteria

- [ ] Frischer EU-Server + `setup-server.sh` + `deploy.sh` → Instanz läuft unter HTTPS (Caddy), Migrationen ausgeführt, Assets gebaut.
- [ ] `compose.prod.yaml` valide; Image-Build ohne Root im Container; Volumes persistieren (DB, privater Storage).
- [ ] CI grün inkl. Build-/Config-Check; Dev-Sail unbeeinflusst.
- [ ] `docs/deploy.md` dokumentiert EU-Regionenwahl, Backup und Rotation (Kundendaten).

## Entscheidungen (Freigabe 2026-09-22)

- **Storage-Default**: lokales Volume für Screenshots/Uploads; S3-kompatibel (EU) als dokumentierter Aufstieg (nicht im Kit eingebaut).
- **`scripts/backup.sh`** (mysqldump + Volume-Tar, idempotent) wird ins Kit aufgenommen.
