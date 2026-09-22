# Lokale Abnahme & Abnahmeprotokoll

Diese Checkliste führt die **nutzerseitige lokale Abnahme** der Schichtplanungs-Template-Erweiterung durch
(Phasen 1–7 inkl. UI-Sichtprüfung). Sie ergänzt die automatisierten Gates (Suite 227 Tests / 754 Assertions,
Pint, PHPStan) um die **manuellen** Prüfungen, die in einer Sandbox ohne Server/DB nicht lauffähig sind.

> **Hinweis:** Die E2E-Tests (`npm run test:e2e`) laufen nur lokal mit laufendem Dev-Server
> (`php artisan serve`) und Datenbank (`.env` = SQLite oder MySQL).

---

## 1. Vorbereitung

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
# .env ggf. auf lokale DB anpassen (Standard: SQLite)
touch database/database.sqlite
php artisan migrate --seed
```

> (optional) Für die Demo-Daten der Referenz-Domäne „demo-shifts":
>
> ```bash
> php artisan db:seed-kiventro-demo --with-history
> ```

---

## 2. Sichtprüfung (Hülle neutral)

| #   | Prüfpunkt                                             | Erwartung                                                                                                                                                         | Ergebnis |
| --- | ----------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------- |
| S1  | `GET /` (Welcome)                                     | Neutrale Landing: App-Name „B2E-Template", **kein** Laravel-Starter-Splash („Let's get started", laravel.com/laracasts.com/cloud.laravel.com-Links, Laravel-Logo) | ☐        |
| S2  | `/admin/login`                                        | **Kein** Demo-Zugangs-Hinweis („kiventro"/Demo-Credentials) unter dem Login-Formular                                                                              | ☐        |
| S3  | Login als Admin (`admin@gf@` … bzw. eigener Admin)    | Dashboard-Head **„Dispatch-Cockpit"**; Panel-Brand **„B2E-Template"**; kein „kiventro"-Branding in Hülle                                                          | ☐        |
| S4  | App-Header/Sidebar (eingeloggt)                       | **Keine** toten Starter-Kit-Links (Search `#`, Repository livewire-starter-kit, Documentation starter-kits)                                                       | ☐        |
| S5  | Tastatur/A11y stichprobenartig                        | Modals (Feedback/Rollback) per Escape schließbar, `role="dialog"` + aria-Labels vorhanden; Tab-Reihenfolge plausibel                                              | ☐        |
| S6  | Responsive stichprobenartig (320 / 768 / 1024 / 1440) | Keine horizontalen Überläufe in Dashboard & Schicht-Tabelle; Mobile-Sidebar nutzbar                                                                               | ☐        |

## 3. Demo-Referenz „demo-shifts" (nach `db:seed-kiventro-demo`)

| #   | Prüfpunkt                                 | Erwartung                                                                                                  | Ergebnis |
| --- | ----------------------------------------- | ---------------------------------------------------------------------------------------------------------- | -------- |
| D1  | Dashboard                                 | Demo-Szenario-Card sichtbar (Hinweis „Demo-Shiftplanung")                                                  | ☐        |
| D2  | `/admin/shifts`                           | Schichten der Demo-Abteilungen sichtbar; Disponent kann KI-Vorschlag annehmen → Zuweisung + Ledger-Eintrag | ☐        |
| D3  | `/admin/my-shifts`                        | Eigene Schichten + Verfügbarkeitsmeldung                                                                   | ☐        |
| D4  | `/admin/audit-log`                        | Audit-Einträge inkl. Hash-Kette; Export CSV/JSON                                                           | ☐        |
| D5  | `/admin/pipeline-settings` (nur WebAdmin) | KI-Pipeline konfigurierbar (nur Web-Administrator)                                                         | ☐        |
| D6  | `/admin/feedback-reports` (nur WebAdmin)  | Feedback-Triage erreichbar                                                                                 | ☐        |

> Abgrenzung: Die Referenz-Domäne gehört NICHT zur neutralen Template-Hülle und ist bewusst als Demo markiert
> (siehe `docs/demo.md`).

## 4. E2E (Playwright)

```bash
# Dev-Server in separatem Terminal:
php artisan serve
# Danach:
npm run test:e2e
```

| #   | Prüfpunkt          | Erwartung                                                                   | Ergebnis |
| --- | ------------------ | --------------------------------------------------------------------------- | -------- |
| E1  | `npm run test:e2e` | Alle Specs grün (smoke inkl. neutralem Titel `B2E-Template`, feedback.spec) | ☐        |

> **Ergebnis-Transkript** (letzte Zeilen der Ausgabe) hier einfügen:
>
> ```
> …
> ```

---

## 5. Fazit

- [ ] Alle Sichtprüfungen bestanden
- [ ] Demo-Referenz geprüft
- [ ] E2E grün
- [ ] Abnahme erteilt / Freigabe für Merge (PR #1)

---

_Protokoll: Datum, Name, ggf. Abweichungen mit Vermerk._
