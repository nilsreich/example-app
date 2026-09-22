# Template lokal / im LAN im Browser ausprobieren (Kurzanleitung)

Diese Anleitung startet das B2E-Template auf deinem Rechner und macht es im lokalen
Netzwerk (`http://<LAN-IP>:8000`) im Browser erreichbar — inklusive Demo-Daten der
Referenz-Domäne „demo-shifts".

> Ausführliche Abnahme (Sichtprüfung S1–S6, Demo D1–D6, E2E E1): `docs/abnahme.md`
> Demo-Konzept & Rollen: `docs/demo.md`

## Voraussetzungen

- PHP ≥ 8.3 (mit `pdo_sqlite`, `mbstring`, `openssl`) und Composer
- Node.js ≥ 20 und npm
- Checkout des Repos (Branch `main`)

## 1. Einmaliges Setup

```bash
composer install
npm ci
cp .env.example .env
php artisan key:generate
# Falls SQLite (Standard): Datenbankdatei anlegen
touch database/database.sqlite
php artisan migrate --seed
npm run build
```

> Alternativ alles in einem Schritt: `composer setup` (installiert, legt `.env` an,
> migriert, baut Assets). Bei bestehendem `.env` entfällt `cp`/`key:generate`.

## 2. LAN-IP ermitteln

```bash
hostname -I        # Linux/macOS → z. B. 192.168.178.200
# ipconfig          # Windows
```

Die IP, die andere Geräte verwenden, steht als erste Adresse. Tipp: Im Router eine
**feste DHCP-Lease** für diesen Rechner vergeben, damit die IP nicht wechselt.

## 3. `.env` für Netzwerkzugriff prüfen

```dotenv
APP_URL=http://192.168.178.200:8000   # ← deine LAN-IP aus Schritt 2
```

Wichtig: `APP_URL` muss zur LAN-IP passen, sonst zeigen Redirects/Login-Links wieder
auf `localhost:8000` und die App ist vom Handy/anderen Geräten aus nicht bedienbar.
Entra-SSO bleibt im Standard deaktiviert (`ENTRA_ENABLED=false`) → lokaler Login.

## 4. Server im Netzwerk starten

```bash
php artisan serve --host 0.0.0.0 --port 8000
```

- Am Rechner selbst: `http://localhost:8000`
- Vom Handy/anderen Geräten im selben Netz: `http://192.168.178.200:8000`

Der in Schritt 1 gebaute Asset-Build reicht vollkommen aus (kein Vite-Dev-Server nötig).
Optional mit Hot-Reload: `npm run dev` zusätzlich starten.

## 5. Demo-Daten laden (Referenz-Domäne „demo-shifts")

```bash
php artisan db:seed-kiventro-demo --with-history
```

Logins (Passwort jeweils `kiventro-demo`):

| E-Mail                         | Rolle                    | Fokus            |
| ------------------------------ | ------------------------ | ---------------- |
| `admin@kiventro.de`            | Web-Admin                | System & Triage  |
| `gf@kiventro.de`               | Geschäftsführung         | ROI zuerst       |
| `leitung.logistik@kiventro.de` | Bereichsleitung Logistik | eigene Abteilung |
| `mitarbeiter@kiventro.de`      | Mitarbeiter              | Self-Service     |

Wichtige Pfade nach dem Login: Dashboard-Cockpit (`/`), Schichten (`/admin/shifts`),
Meine Schichten (`/admin/my-shifts`), ROI-Dashboard (`/admin`), KI-Pipeline
(`/admin/pipeline-settings`), Audit-Log (`/admin/audit-log`).

## Troubleshooting

| Problem                                      | Lösung                                                                       |
| -------------------------------------------- | ---------------------------------------------------------------------------- |
| Vom anderen Gerät keine Verbindung           | Firewall: Port 8000 freigeben, z. B. `sudo ufw allow 8000` (Linux)           |
| Login-Links zeigen auf `localhost`           | `APP_URL` in `.env` auf die LAN-IP setzen, danach `php artisan config:clear` |
| Server nach Reboot wieder starten            | IP mit `hostname -I` erneut prüfen (DHCP); ggf. feste Lease im Router        |
| „No application encryption key"              | `php artisan key:generate` ausführen                                         |
| SQLite-Fehler „database file does not exist" | `touch database/database.sqlite` und `php artisan migrate` ausführen         |
