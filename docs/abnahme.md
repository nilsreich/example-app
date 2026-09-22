# Lokale Abnahme & Abnahmeprotokoll

Diese Checkliste führt die **nutzerseitige lokale Abnahme** der Schichtplanungs-Template-Erweiterung
(Phasen 1–7 inkl. UI-Sichtprüfung) durch. Sie ergänzt die automatisierten Gates
(Suite 257 Tests / 819 Assertions, Pint, PHPStan level 7 **ohne Baseline**) um die
**manuellen** Prüfungen, die in einer Sandbox ohne Server/DB nicht lauffähig sind.

> **Hinweis:** Die E2E-Tests starten den Dev-Server (`php artisan serve`, Port 8000)
> jetzt selbst über den `webServer`-Block in `playwright.config.ts`. Sie laufen auch in
> der CI (`E2E tests`-Step in `.github/workflows/tests.yml`). Voraussetzung ist eine
> migrierte Datenbank (`.env` = SQLite oder MySQL).

---

## 1. Vorbereitung

```bash
composer setup          # installiert PHP+JS-Abhängigkeiten, legt .env an,
                        # key:generate, migrate --force, npm ci, npm run build
# Alternativ manuell:
# composer install && npm ci && cp .env.example .env && php artisan key:generate
# touch database/database.sqlite && php artisan migrate --seed
```

> (optional) Für die Demo-Daten der Referenz-Domäne „demo-shifts":
>
> ```bash
> php artisan db:seed-kiventro-demo --with-history
> ```

---

## 2. Sichtprüfung (Hülle neutral)

| #   | Prüfpunkt                                             | Erwartung                                                                                                                                                                                                                           | Ergebnis |
| --- | ----------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------- |
| S1  | `GET /` (Welcome)                                     | Neutrale Landing: App-Name „B2E-Template", **kein** Laravel-Starter-Splash („Let's get started", laravel.com/laracasts.com/cloud.laravel.com-Links, Laravel-Logo); Hell-/Dunkelmodus folgt der Systemeinstellung (kein Zwangs-Dark) | ☐        |
| S2  | `/admin/login`                                        | **Kein** Demo-Zugangs-Hinweis („kiventro"/Demo-Credentials) unter dem Login-Formular                                                                                                                                                | ☐        |
| S3  | Login als Admin (`admin@gf@` … bzw. eigener Admin)    | Dashboard-Head **„Dispatch-Cockpit"**; Panel-Brand **„B2E-Template"**; kein „kiventro"-Branding in Hülle                                                                                                                            | ☐        |
| S4  | App-Header/Sidebar (eingeloggt)                       | **Keine** toten Starter-Kit-Links (Search `#`, Repository livewire-starter-kit, Documentation starter-kits)                                                                                                                         | ☐        |
| S5  | Tastatur/A11y stichprobenartig                        | Modals (Feedback/Rollback) per Escape schließbar, `role="dialog"` + aria-Labels vorhanden; Tab-Reihenfolge plausibel                                                                                                                | ☐        |
| S6  | Responsive stichprobenartig (320 / 768 / 1024 / 1440) | Keine horizontalen Überläufe in Dashboard & Schicht-Tabelle; Mobile-Sidebar nutzbar                                                                                                                                                 | ☐        |

## 3. Demo-Referenz „demo-shifts" (nach `db:seed-kiventro-demo`)

| #   | Prüfpunkt                                 | Erwartung                                                                                                                                                                    | Ergebnis |
| --- | ----------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------- |
| D1  | Dashboard                                 | Demo-Szenario-Card sichtbar (Hinweis „Demo-Shiftplanung")                                                                                                                    | ☐        |
| D2  | `/admin/shifts`                           | Schichten der Demo-Abteilungen sichtbar; Disponent kann KI-Vorschlag annehmen → Zuweisung + Ledger-Eintrag                                                                   | ☐        |
| D3  | `/admin/my-shifts`                        | Eigene Schichten + Verfügbarkeitsmeldung                                                                                                                                     | ☐        |
| D4  | `/admin/audit-log`                        | Audit-Einträge inkl. Hash-Kette (Status „gültig"); Export CSV/JSON **wird selbst als `exported`-Event protokolliert**; CSV-Zellen mit führendem `= + - @` sind neutralisiert | ☐        |
| D5  | `/admin/pipeline-settings` (nur WebAdmin) | KI-Pipeline konfigurierbar (nur Web-Administrator)                                                                                                                           | ☐        |
| D6  | `/admin/feedback-reports` (nur WebAdmin)  | Feedback-Triage erreichbar                                                                                                                                                   | ☐        |

> Abgrenzung: Die Referenz-Domäne gehört NICHT zur neutralen Template-Hülle und ist bewusst als Demo markiert
> (siehe `docs/demo.md`).

## 3b. Verhaltensänderungen aus dem Review-Fix (PR #2)

Die folgenden Punkte wurden bewusst verschärft — bei der Abnahme bitte gegenprüfen:

| #   | Prüfpunkt                                  | Erwartung                                                                                                           | Ergebnis |
| --- | ------------------------------------------ | ------------------------------------------------------------------------------------------------------------------- | -------- |
| V1  | Demo-Reset auf `/admin/shifts`             | Action „Demo-Szenario neu laden" ist **nur für Web-Admins sichtbar**; GF/Bereichsleitung sehen sie nicht            | ☐        |
| V2  | Schicht zuweisen ohne Pflichtqualifikation | Aktion wird **abgelehnt** (Fehlermeldung nennt die fehlende Qualifikation) statt still unqualifiziert zuzuweisen    | ☐        |
| V3  | Schicht-Formular (`/admin/shifts`)         | `Status` und `Mitarbeiter` sind **nicht mehr frei editierbar** — Übergänge nur über Vorschlag annehmen/Zurückrollen | ☐        |
| V4  | Schicht löschen                            | Löschen läuft über „Löschen (protokolliert)" und erzeugt einen Audit-Eintrag                                        | ☐        |
| V5  | Registrierung (`/register`)                | Neuer Account erhält die Rolle **„Nutzer"** und kann sich danach einloggen (kein 500)                               | ☐        |
| V6  | SSO-Konto + „Passwort vergessen"           | Für Konten mit `entra_object_id` wird kein lokaler Passwort-Reset angeboten/akzeptiert                              | ☐        |
| V7  | `ENTRA_ENABLED=false`                      | `/auth/entra/redirect` liefert **404** (SSO-Routen inaktiv)                                                         | ☐        |

## 4. E2E (Playwright)

```bash
# Der Dev-Server wird automatisch gestartet (webServer in playwright.config.ts):
npm run test:e2e
# Gegen eine bereits laufende Instanz / andere URL:
PLAYWRIGHT_BASE_URL=http://localhost:8000 npm run test:e2e
```

> Die Specs nutzen den deterministischen **Mock**-Treiber (kein echter Provider-Call).

| #   | Prüfpunkt          | Erwartung                                                                            | Ergebnis |
| --- | ------------------ | ------------------------------------------------------------------------------------ | -------- |
| E1  | `npm run test:e2e` | Alle Specs grün (smoke: neutraler Titel `B2E-Template` + Login-Seite, feedback.spec) | ☐        |

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
- [ ] Abnahme erteilt / Freigabe für Merge (PR #2 — gemergt am 2026-09-22, Merge-Commit `326f83a`)

---

_Protokoll: Datum, Name, ggf. Abweichungen mit Vermerk._
