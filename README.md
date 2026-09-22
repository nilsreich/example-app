# B2E-Template

Template für B2E-Webanwendungen auf **Laravel ^13 + Filament ^5 (PHP ^8.3)**.
Neutrale Template-Hülle plus eine klar als Demo markierte Referenz-Domäne
**„demo-shifts"** (Schichtplanung), die die Module Identität, Audit-Ledger,
In-App-Feedback und den KI-Erweiterungspunkt an einem durchgängigen Beispiel
durchspielt.

## Quick Start (lokal / im LAN im Browser)

Kurzanleitung Schritt für Schritt: [docs/run-kurz.md](docs/run-kurz.md)

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
npm run build
php artisan serve --host 0.0.0.0 --port 8000
# Demo-Daten laden: php artisan db:seed-kiventro-demo --with-history
```

Danach im Browser: `http://localhost:8000` — von anderen Geräten im selben Netz
`http://<LAN-IP>:8000` (siehe Kurzanleitung für `APP_URL` und Firewall-Hinweise).

> Alles in einem Schritt: `composer setup` (installiert, legt `.env` an, migriert, baut Assets).

## Commands

| Command                | Beschreibung                                            |
| ---------------------- | ------------------------------------------------------- |
| `composer setup`       | Einmaliges Setup (install, `.env`, key, migrate, build) |
| `composer dev`         | Laravel-Dev-Server (+ Queue-Worker)                     |
| `npm run dev`          | Vite-Dev-Server (Hot-Reload; optional)                  |
| `npm run build`        | Frontend-Assets bauen                                   |
| `composer lint`        | Pint: formatieren                                       |
| `composer lint:check`  | Pint: prüfen (CI)                                       |
| `composer types:check` | PHPStan, Level 7 — **ohne Baseline** (0 Fehler Pflicht) |
| `composer test`        | lint:check + types:check + Test-Suite                   |
| `composer ci:check`    | CI-Gate (Test-Suite)                                    |
| `npm run test:e2e`     | Playwright-E2E (Dev-Server erforderlich)                |

## Architektur & Entscheidungen

- **Modul-Index & globale Annahmen:** [CAPABILITY-MAP.md](CAPABILITY-MAP.md)
- **Modul-Specs:** `SPEC-identity.md`, `SPEC-audit.md`, `SPEC-feedback.md`, `SPEC-ai.md`, `SPEC-deploy.md`, `SPEC-demo-shifts.md`
- **Intent / Produktgedanke:** [docs/intent/b2e-template.md](docs/intent/b2e-template.md)
- **Referenz-Domäne „demo-shifts":** [docs/demo.md](docs/demo.md)
- **Produktions-Deploy (EU, Docker):** [docs/deploy.md](docs/deploy.md)
- **KI-Modul & AI-Doku:** [docs/ai.md](docs/ai.md)
- **Lokale Abnahme (Sichtprüfung + E2E):** [docs/abnahme.md](docs/abnahme.md)

Die Doku konsolidiert Entscheidungen (statt separater ADR-Dateien) in den oben
verlinkten Specs und Capability-Map — bestehende Repo-Konvention.

## Konventionen

- UI/App-Sprache **Deutsch**; Code/Kommentare **Englisch**; Commit-Messages **Deutsch**.
- Qualitäts-Gates (CI): Pint, PHPStan Level 7 **ohne Baseline-Ausnahme**, volle
  Test-Suite; E2E via Playwright lokal.
- DB: SQLite/MySQL 8.4 (lokal/test), MySQL 8.4 + Redis (Produktion), siehe Specs.
