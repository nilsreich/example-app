# Deployment & Betrieb (Produktion)

> Modul-Id: `deploy` · Spec: `SPEC-deploy.md` · Status: Phase 6 umgesetzt (T15–T17).

Dieses Dokument beschreibt das **EU-fähige Produktions-Setup**: Docker-Compose-Stack
mit Caddy (Auto-HTTPS via Let's Encrypt), MySQL 8.4, Redis 7 und einer
Multi-Stage-Image-Build-Pipeline. Das Deployment ist **vollständig über
Umgebungsvariablen** konfiguriert; es liegen keine Secrets im Repository.

## Architektur

```
┌─────────────────────────────────────────────── Server (EU-Region) ─┐
│                                                                      │
│  Caddy (caddy:2-alpine)  ─── HTTPS 80/443 ─── app (php:8.4-fpm)     │
│  ◦ Auto-TLS Let's Encrypt   reverse_proxy   ◦ fpm clear_env=no      │
│  ◦ Sicherheits-Header        app:9000        ◦ opcache (validate=0)  │
│  ◦ (kein SSE-Streaming)                      ◦ Non-Root: www-data   │
│        │                                        │       │           │
│        │   mysql:8.4 (DB-Daten)   worker (queue) │   redis:7-alpine │
│        │   ◦ nur intern            ◦ gleiches     │   (Cache/Sess., │
│        │   (kein Port)             Image/Env     │    nur intern)   │
│        └─────────────── Bridge-Netz „b2e“ ──────────────────────────┘
│   Volumes: app-storage (Screenshots/Uploads), db-data, redis-data,  │
│            caddy-data, caddy-config                                  │
└──────────────────────────────────────────────────────────────────────┘
```

- **Alle Dienste** (außer Caddy) exposen **keine Ports** nach außen — nur Caddy
  erreicht den App-Container über das interne Bridge-Netz.
- **Nicht-Root**: App- und Worker-Container laufen als `www-data`
  (`USER www-data` im Image).
- **Sicherheits-Rails**: `APP_ENV=production` und `APP_DEBUG="false"` werden in
  `compose.prod.yaml` als Overrides erzwungen (unabhängig von lokalen .env-Werten).

## Hosting in der EU

Für GoBD/DSGVO-Konformität sollte der Server in der EU betrieben werden.
Geeignete Regionen/Anbieter (Beispiele):

| Anbieter      | EU-Regionen                   | Hinweise                |
| ------------- | ----------------------------- | ----------------------- |
| Hetzner Cloud | Falkenstein/FSN, Nürnberg/NBG | Günstig, EU-Datacenter  |
| IONOS Cloud   | DE/Frankfurt u. a.            | Deutscher Anbieter      |
| OVHcloud      | Gravelines/SBG, Frankfurt     | Französischer Anbieter  |
| Scaleway      | Paris/PL-WAW, Amsterdam/AMS   | EU-eigene Infrastruktur |

**Mindestanforderung:** 2 vCPU / 2 GB RAM / 20 GB SSD, Ubuntu 24.04 LTS,
öffentliche IPv4, DNS-Setup-Rechte für die Domain.

## Schnellstart

### 1. Server vorbereiten

```bash
sudo apt-get update && sudo apt-get install -y git
git clone <ssh-or-https-repo-url> /opt/b2e-template
cd /opt/b2e-template
sudo ./scripts/setup-server.sh        # Docker Engine + Compose-Plugin, idempotent
```

Anschließend **DNS**: A-Record (`app.b2e-template.example` → Server-IP) anlegen
und Ports 80/443 in der Firewall freigeben.

### 2. `.env` anlegen

Der Stack liest die Konfiguration ausschließlich aus der Datei **`.env`** im
Repo-Root (per `env_file` eingebunden). Lege sie als Kopie der Vorlage an und
fülle alle Werte aus — **niemals** Werte wie `change-me` oder echte Secrets
committen (siehe `.dockerignore`, `.gitignore`):

```bash
cp .env.production.example .env    # falls vorhanden (siehe Sperrhinweis unten)
$EDITOR .env
```

> **Hinweis (Sicherheits-Netzer #1):** Die Datei `.env.production.example`
> konnte von den Automations-Agenten **nicht angelegt** werden (zugriffsgesperrte
> Dateipfad-Regel für `.env`-Varianten). Die vollständige, einsatzbereite Vorlage
> steht deshalb **weiter unten** in diesem Dokument – einfach als
> `.env.production.example` im Repo speichern bzw. direkt als `.env` verwenden.

### 3. Deployment ausführen

```bash
./scripts/deploy.sh
                # baut das Image, startet den Stack (--wait auf Healthchecks),
                # führt Migrationen aus und baut die optimierten Caches.
# Nur Starten/Migrieren ohne Build:
B2E_SKIP_BUILD=1 ./scripts/deploy.sh
```

Danach ist die Anwendung erreichbar unter `https://<APP_DOMAIN>` –
einschließlich `/up`-Healthcheck (von Caddy für den Health-Status geprüft).

## Umgebungsvariablen

### Referenz (Kurzfassung)

| Variable                                              | Zweck                                                                                                         | Beispiel                           |
| ----------------------------------------------------- | ------------------------------------------------------------------------------------------------------------- | ---------------------------------- |
| `APP_ENV`                                             | Muss `production` sein (wird erzwungen)                                                                       | `production`                       |
| `APP_URL`                                             | Öffentliche Basis-URL                                                                                         | `https://app.b2e-template.example` |
| `APP_LOCALE`                                          | UI-Sprache                                                                                                    | `de`                               |
| `APP_KEY`                                             | Laravel-Key (leer lassen → `deploy.sh`-Guard verlangt gültigen Wert; per `php artisan key:generate` erzeugen) | `base64:…`                         |
| `DB_*` / `MYSQL_*`                                    | MySQL-Verbindung (Host `mysql`, Port 3306)                                                                    | s. u.                              |
| `SESSION_DRIVER` / `CACHE_STORE` / `QUEUE_CONNECTION` | Redis bzw. database                                                                                           | `redis` / `redis` / `database`     |
| `APP_DOMAIN`                                          | Caddy-Servername (ohne Schema)                                                                                | `app.b2e-template.example`         |
| `ACME_EMAIL`                                          | Let's-Encrypt-Benachrichtigungsadresse                                                                        | `ops@example.de`                   |
| `ENTRA_*`                                             | Entra-ID-SSO (s. `docs/` zum identity-Modul)                                                                  | s. u.                              |
| `AI_AGENT_DRIVER`                                     | `mock` (Default) oder `laravel-ai`                                                                            | `mock`                             |
| `FILESYSTEM_DISK`                                     | `local` (Default) oder `s3` (EU-Bucket)                                                                       | `local`                            |

### Vollständige Vorlage (`env.production.example`)

> Dieser Block ist die **einzige offizielle Produktions-Vorlage**. Am Server
> einfach als `.env` speichern und ausfüllen.

```dotenv
# ---------- App ----------
APP_NAME="B2E-Template"
APP_ENV=production
# APP_KEY wird von deploy.sh geprüft; Erzeugung: docker compose exec app php artisan key:generate --force
APP_KEY=
APP_DEBUG=false
APP_URL=https://app.b2e-template.example

APP_LOCALE=de
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=de_DE

APP_MAINTENANCE_DRIVER=file

# ---------- Logs ----------
LOG_CHANNEL=stack
LOG_STACK=daily
LOG_DAILY_DAYS=30
LOG_LEVEL=error

# ---------- Datenbank (identisch zu MYSQL_*; Host 'mysql' ist der Compose-Dienst) ----------
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=laravel
DB_PASSWORD=change-me

MYSQL_DATABASE=laravel
MYSQL_USER=laravel
MYSQL_PASSWORD=change-me
MYSQL_ROOT_PASSWORD=change-me

# ---------- Session / Cache / Queue ----------
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_DOMAIN=

CACHE_STORE=redis
# CACHE_PREFIX=

REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=null

# Queue: 'database' – der worker-Dienst in compose.prod.yaml verarbeitet die
#          Jobs asynchron (Checkpoint-6-Entscheidung: Worker sofort mitliefern).
QUEUE_CONNECTION=database

BROADCAST_CONNECTION=log

# ---------- Mail ----------
# Default: log (kein Versand). Postfix/SMTP-Provider ergänzen, sobald E-Mails
# (z. B. Password-Reset via Filament/Fortify) im Einsatz sind:
MAIL_MAILER=log
# MAIL_MAILER=smtp
# MAIL_HOST=smtp.example.de
# MAIL_PORT=587
# MAIL_USERNAME=
# MAIL_PASSWORD=
# MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@b2e-template.example
MAIL_FROM_NAME="${APP_NAME}"

# ---------- Dateispeicher ----------
# Default: lokales app-storage-Volume (Screenshots/Uploads sind dann auf dem
# Server gesichert – siehe scripts/backup.sh). Optional: S3-kompatibler
# Objektspeicher in der EU (z. B. Hetzner Object Storage, exoscale, 1&1):
FILESYSTEM_DISK=local
# FILESYSTEM_DISK=s3
# AWS_ACCESS_KEY_ID=change-me
# AWS_SECRET_ACCESS_KEY=change-me
# AWS_DEFAULT_REGION=eu-central-1
# AWS_BUCKET=change-me
# AWS_USE_PATH_STYLE_ENDPOINT=false
# AWS_ENDPOINT=https://s3.eu-central-1.amazonaws.com

# ---------- Entra-ID-SSO (identity-Modul) ----------
ENTRA_ENABLED=true
ENTRA_TENANT_ID=change-me
ENTRA_CLIENT_ID=change-me
ENTRA_CLIENT_SECRET=change-me
ENTRA_REDIRECT_URI=/auth/entra/callback
ENTRA_SCOPE=openid,profile,email,User.Read

# ---------- AI (Erweiterungspunkt) ----------
AI_AGENT_DRIVER=mock
# Für echte Provider (Laravel-AI-SDK; openai endpunktfrei EU-konfigurierbar):
# OPENAI_API_KEY=change-me
# OPENAI_URL=https://api.openai.com/v1   # alternativ EU-/On-Prem-Endpunkt

# ---------- Deployment / Caddy ----------
APP_DOMAIN=app.b2e-template.example
ACME_EMAIL=change-me
# CIDR(s) des Reverse-Proxy(s), deren X-Forwarded-* vertraut wird (kommagetrennt).
# WICHTIG: Muss dem tatsächlichen Docker-Compose-Bridge-Subnetz entsprechen
# (siehe compose.prod.yaml: networks.b2e.ipam.config.subnet, Standard 172.28.0.0/24).
# Docker vergibt ohne explizite Angabe 172.16.0.0/12-Subnetze — 10.0.0.0/8 passt NICHT.
# Bei Bare-Metal-Caddy die Server-IP eintragen.
TRUSTED_PROXIES=172.28.0.0/24

# ---------- Sonstiges ----------
BCRYPT_ROUNDS=12
```

### Sicherheits-Netzer & Gates im `deploy.sh`

`scripts/deploy.sh` verweigert den Start, solange:

- `APP_KEY` keine gültige `base64:`-Kodierung hat,
- `APP_DOMAIN`, `ACME_EMAIL`, `DB_PASSWORD`, `MYSQL_PASSWORD`,
  `MYSQL_ROOT_PASSWORD`, `MYSQL_DATABASE`, `MYSQL_USER` leer sind oder noch
  `change-me` enthalten,
- bei `ENTRA_ENABLED=true` die Entra-Pflichtwerte fehlen.

Damit gelangt kein Platzhalter-Setup in Produktion.

## Betrieb

### Backup & Rotation

```bash
/opt/b2e-template/scripts/backup.sh
# BACKUP_DIR=/mnt/backup KEEP=14 /opt/b2e-template/scripts/backup.sh
```

- Erzeugt `database-<TS>.sql.gz` (mysqldump, `--single-transaction`) und
  `storage-<TS>.tar.gz` (app-storage-Volume, enthält u. a. Screenshots).
- Rotiert auf `KEEP` (Default 7) älteste Sicherungen jeder Art.
- Cron (GoBD: tägliche, dokumentierte Sicherung):

```cron
0 2 * * * /opt/b2e-template/scripts/backup.sh >> /var/log/b2e-backup.log 2>&1
```

- **Off-Site**: `BACKUP_DIR` auf ein externes/abgesetztes Ziel legen (NFS-Mount,
  dediziertes Backup-Volume oder verschlüsselter Objektspeicher), damit der
  Backup-Zielort nicht mit dem Server ausfällt.

### Updates / neue Version einspielen

```bash
cd /opt/b2e-template
git pull          # neue Commits ziehen
./scripts/deploy.sh
```

`deploy.sh` baut ein neues Image, Compose erkennt die geänderte Image-ID und
erzeugt neue Container (frischer opcache-Worker durch neuen Container). Danach
Migrationen + `optimize`. **Kein** `composer`/`npm` auf dem Server nötig – alles
passiert im Build (Multi-Stage).

### Rollback

1. Früheren Stand wiederherstellen: `git checkout <vorheriger-tag/commit>`
   (oder Sicherungs-Volume via `backup.sh` einspielen).
2. `./scripts/deploy.sh` (baut das alte Image erneut).
3. Falls nur die **Daten** zurück müssen: `db-data`-Volume ersetzen und Dump
   aus der Sicherung einspielen (Restore-Doku: `gunzip < database-<TS>.sql.gz | 
docker compose -f compose.prod.yaml exec -T mysql sh -c 'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'`).

### Logs / Monitoring

```bash
docker compose -f compose.prod.yaml logs -f --tail=100 app   # Laravel-Logs
docker compose -f compose.prod.yaml logs -f --tail=50  caddy # Caddy/ACME
docker compose -f compose.prod.yaml ps                        # Health-Status
curl -fsS https://<APP_DOMAIN>/up                             # Healthcheck
```

Tägliche Log-Rotation über `LOG_STACK=daily` / `LOG_DAILY_DAYS=30`.

## Hintergrund-Worker (Queue)

Der Stack startet **einen separaten Worker-Dienst** (`worker`, Checkpoint-6-
Entscheidung „mit Worker"). Er nutzt dasselbe Image/dieselbe `.env` wie `app`,
wartet auf MySQL+Redis (service_healthy) und verarbeitet
`QUEUE_CONNECTION=database`-Jobs asynchron:

```yaml
# Auszug aus compose.prod.yaml (Dienst 'worker'):
worker:
    build:
        context: .
        dockerfile: Dockerfile.prod
    image: b2e-template/app:prod
    init: true
    restart: unless-stopped
    command:
        [
            "php",
            "artisan",
            "queue:work",
            "--sleep=3",
            "--tries=3",
            "--max-time=3600",
        ]
    env_file: [{ path: .env, required: false }]
    environment: { APP_ENV: production, APP_DEBUG: "false" }
    depends_on:
        mysql: { condition: service_healthy }
        redis: { condition: service_healthy }
    volumes:
        - app-storage:/var/www/html/storage
```

`--max-time=3600` erzwingt einen sauberen Worker-Neustart nach einer Stunde
(Speicher/Ressourcen-Rotation); `restart: unless-stopped` plant ihn automatisch
neu. Skalieren (mehr Worker): `docker compose -f compose.prod.yaml up -d --scale worker=2`.

## Mail

Default ist `MAIL_MAILER=log` (nichts wird versendet). Für Password-Reset &
Notifications einen SMTP-Provider (z. B. Postmark, Brevo, Hetzner Mail, eigener
Exchange) in der `.env` eintragen.

> **Checkpoint-6-Entscheidung:** Mail-Provider = **log** (kein Versand) bis Bedarf
> entsteht; Konfiguration ist oben vorbereitet.

## Sicherheits-Hinweise

- **Secrets**: liegen nur in `.env` (Server) bzw. GitHub Secrets/SOPS – niemals
  im Repo. `.dockerignore`/`.gitignore` schließen `.env*`, `vendor/`,
  `node_modules/`, Backups aus.
- **CSP bewusst nicht gesetzt**: Filament/Flux benötigen Inline-Skripte/-Stile;
  ein restriktiver `Content-Security-Policy`-Header würde das Admin-UI
  brechen. Die übrigen Header (`HSTS`, `nosniff`, `frame-guard`,
  `referrer-policy`, `permissions-policy`) setzt Caddy bereits.
- **Hidden im Stack**: MySQL/Redis publishieren keine Ports; nur Caddy ist
  erreichbar. Hinter Caddy werden `X-Forwarded-*` vertraut
  (`trustProxies` in `bootstrap/app.php`), damit HTTPS-URLs/ROI-Deeplinks
  korrekt erzeugt werden.
- **opcache** ist mit `validate_timestamps=0` konfiguriert → nach Updates immer
  `php artisan optimize` (macht `deploy.sh` automatisch).
- **Entra-SSO**: `ENTRA_CLIENT_SECRET` niemals committen; Rollen-Mapping in
  `config/entra.php` vor dem ersten Login prüfen (Kein-Treffer = Login abgelehnt).

## Troubleshooting

| Symptom                                                 | Ursache / Lösung                                                                                                                                                              |
| ------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `502` hinter Caddy                                      | App-Container nicht healthy: `docker compose ps`, Logs unter `logs app`; start_period 20s abwarten.                                                                           |
| Healthcheck `app` rot                                   | `fsockopen(127.0.0.1, 9000)` erreicht fpm nicht → `clear_env=no` prüfen (`zz-container-env.conf`).                                                                            |
| `X-Forwarded-*` wird ignoriert / http- statt https-URLs | `TRUSTED_PROXIES`-Env (bootstrap/app.php) deckt die Caddy-Quell-IP nicht ab bzw. Caddy-`header_up`-Block fehlt.                                                               |
| alte Views/Konfig trotz neuem Code                      | opcache `validate_timestamps=0` → `./scripts/deploy.sh` (führt `optimize` aus), kein manuelles Cache-Clearing nötig.                                                          |
| `SQLSTATE[HY000] [2002]`                                | DB_HOST muss `mysql` (Compose-Dienstname) sein, nicht localhost.                                                                                                              |
| Let's-Encrypt-Rate-Limit                                | Erst nach korrektem DNS starten; Test-ACME (`ca=https://acme-staging-v02.api.letsencrypt.org/directory`) in `Caddyfile.prod` einblendbar.                                     |
| Permission-Denied auf storage                           | Erst-Start des Volumes erbt Ownership via `chown www-data` (Build); vorhandenes Volume manuell: `docker compose exec app chown -R www-data:www-data storage bootstrap/cache`. |

## CI/CD

`.github/workflows/tests.yml` (Push auf `main` + Pull Requests):

1. `ci`-Job: PHP 8.5 + Node 22, `composer setup`, `composer ci:check`
   (Pint → PHPStan → PHPUnit, SQLite :memory:).
2. `deploy-artifacts`-Job: `docker compose -f compose.prod.yaml config --quiet`
   (Konfiguration valide) + `docker build -f Dockerfile.prod` (Image-Build-Check,
   Layer-Cache via GHA).

Echte Deployments sind **bewusst nicht** in der CI automatisiert (Ask-first):
Upload/Deploy passiert manuell via `scripts/deploy.sh` auf dem Server.
