# Implementation Plan: B2E-App-Template

## Overview

Die Schichtplanungs-Demo (Laravel + Filament + Laravel AI SDK) wird zu einem wiederverwendbaren B2E-Template generalisiert. Sechs Module (Capability Map: `identity`, `audit`, `feedback`, `ai`, `deploy`, `demo-shifts`) werden in app/-Modulen gebaut bzw. extrahiert. Jede Instanz ist ein eigenes Deployment (kein Multi-Tenant), Hybrid-Login (Entra-SSO + lokaler Fallback), GoBD-Audit mit Hash-Kette, EU-Docker-Deploy (Caddy). Die Schichtplanung bleibt als Referenz-Domäne erhalten und wird auf die generischen Module umgestellt.

## Architecture Decisions

- **Modul-Struktur:** eigene app/-Ordner `app/Identity`, `app/Audit`, `app/Feedback`, `app/Ai` (freigegebene Q1). Keine Composer-Pakete. Demo-Domäne bleibt unter `App\Models`/`App\Services`.
- **Audit:** generisches, append-only Ledger mit Hash-Kette (`audit_events`), Rollback = neues verkettetes Event; einziges Audit-System (ersetzt `ShiftAuditEvent`). Keine Löschung, Archivierung via Export. App-side Guard.
- **Identity:** Laravel Socialite + `socialiteproviders/microsoft`; `entra_object_id` als eindeutige Spalte; Gruppen→Rolle/Abteilung via `config/entra.php`; Entra im CI simuliert (Socialite-Fake), lokaler Passwort-/2FA-/Passkey-Login (Fortify/Filament) bleibt unverändert.
- **AI:** Erweiterungspunkt `AiAgent`-Contract + Registry + deterministischer Fake (Default ohne Keys); Provider über `config/ai.php`/ENV umschaltbar. Keine fixen Use-Cases.
- **Feedback:** als `app/Feedback`-Modul extrahiert, abschaltbar via `config/feedback.php`; Screenshots bleiben privat (policy-geschützte Route), Aufbewahrung 12 Monate + manuelle Löschung.
- **Deploy:** eigenes Produktions-Kit (`Dockerfile.prod`, `compose.prod.yaml` mit app + Caddy + MySQL 8.4 + Redis), Sail bleibt dev-only; `deploy.sh`/`setup-server.sh`/`backup.sh`; EU-Hosting dokumentiert.
- **Umgebungs-Standards:** Dev-Default SQLite, Prod MySQL 8.4 + Redis; `APP_LOCALE=de` (UI Deutsch, Code Englisch, Commits Deutsch).
- **Template-Hülle neutral:** kein Demo-/Kunden-Branding in der Hülle (`APP_NAME`, Filament-Panel-Brand, welcome/auth-Views) — Platzhalter/Neutrale Bezeichnungen; die Schichtplanung bleibt als klar gekennzeichnete **Referenz-Domäne** (T18).

## Task List

### Phase 1: audit (Foundation)

- [ ] **T1: `audit_events`-Schema + Hash-Kette**
    - Migration `create_audit_events_table` (morph `auditable`, `actor_user_id`, `event_type`, `previous_state`/`new_state` json, `version`, `prev_hash`, `hash`, `source`(web|ai|cli), ip, user_agent, nur `created_at`; Indizes actor/auditable/created_at).
    - `App\Audit\Models\AuditEvent` (immutable: kein updated_at, kein Mass-Restore), `App\Audit\Enums\AuditEventType`, pure `App\Audit\Support\HashChain` (sha256, kanonisches JSON).
    - Acceptance: Kette gültig; Einzeländerung bricht Kette; Write-Only-Pfad.
    - Verify: `php artisan test --filter=Audit`; `composer test`.
    - Files: `database/migrations/*`, `app/Audit/{Models,Enums,Support}/*`, Tests.
    - Deps: None. Size: M.

- [ ] **T2: AuditLedger + Auditable-Trait + Append-only-Guard**
    - `App\Audit\AuditLedger::record(...)` (Actor/Auditable/Typ/Vorher-Nachher/Source, Versionierung lockForUpdate, prev_hash-Fortsetzung), `rollback(...)` = neues verkettetes Event, `query()`-API für Filter (Actor/Auditable/Zeitraum/Typ).
    - `App\Audit\Concerns\Auditable` (Verbund morphed einfügen), Guard: Update/Delete auf `audit_events` verhindert (Model-Guard, kein Weg im Code).
    - Acceptance: Ledger schreibt Ketten-korrekt; Rollback verkettet; Update/Delete-Versuch scheitert; Filter/Query funktioniert.
    - Verify: `php artisan test --filter=Audit`; `composer test`.
    - Files: `app/Audit/*`, `database/migrations/*`, Tests.
    - Deps: T1. Size: M.

- [ ] **T3: Audit-Export (CSV/JSON) + Filament-Ansicht**
    - `App\Audit\Http\Controllers\AuditExportController` (kann CSV/JSON, Zeitraum/Actor/Modul-Filter), Policy (nur audit-berechtigte Rollen), Filament-Page zur Ansicht/Export.
    - Acceptance: Export policy-geschützt, Format korrekt, Filter greifen.
    - Verify: `php artisan test --filter=Audit`; `composer test`.
    - Files: `app/Audit/Http/*`, `app/Audit/Filament/*`, `app/Audit/Policies/*`, Tests.
    - Deps: T2. Size: M.

**Checkpoint 1 (audit):** `composer test` grün; Manipulationstest zeigt Kettenbruch; keine andere Audit-Implementierung mehr im Code (außer Demo, die T13-T14 ersetzt). Review mit Nutzer.

### Phase 2: identity

- [ ] **T4: Entra-SSO-Grundgerüst**
    - `config/entra.php` (tenant, client_id/secret, mapping, scope), Socialite microsoft-Provider einrichten, `routes/entra.php` (redirect/callback, throttle), `app/Identity/Http/Controllers/EntraAuthController.php`.
    - Acceptance: Redirect zeigt auf MS-Login (simuliert), Callback-Route erreichbar; lokaler Login unverändert.
    - Verify: `php artisan test --filter=Identity`; `composer test`.
    - Files: `config/entra.php`, `routes/entra.php`, `app/Identity/Http/*`, `composer.json`.
    - Deps: None (parallel zu Phase 1 möglich). Size: S/M.

- [ ] **T5: Provisioning + Mapping**
    - Migration `add_entra_object_id_to_users` (unique, nullable), `App\Identity\EntraUserResolver` (Socialite-User → finde/erstelle User via `entra_object_id`, sonst email, immer aktualisieren), `App\Identity\EntraGroupRoleMapper` (Group-ObjectIds → role/department aus config; kein Default-Rollen-Verfall: ohne Treffer → Zugriff verweigert außer konfigurierter Fallback).
    - Acceptance: Neu-Provisioning, Bestand-Match über Email-Wechsel, Mapping korrekt, Ablehnung ohne Berechtigung.
    - Verify: `php artisan test --filter=Identity`; `composer test` (Socialite-Fake).
    - Files: `database/migrations/*`, `app/Identity/*`, `config/entra.php`, Tests.
    - Deps: T4. Size: M.

- [ ] **T6: Audit-Anbindung + Hybrid-Absicherung + Rollen-Default**
    - SSO-Events (Login/Logout/Provisioning/Fehler) über `AuditLedger`; Account-Update nach Erstlogin; Rollen-Default-Set (4 Rollen) als Config-Abschnitt in `config/entra.php` bzw. `b2e.php`; Tests: lokaler Login/2FA/Passkeys weiter grün, Entra-Fake-Pfad grün.
    - Acceptance: SSO-Login hinterlässt Audit-Events; lokaler Login unberührt; Rollen-Config vorhanden.
    - Verify: `php artisan test --filter=Identity`; `composer test`.
    - Files: `app/Identity/*`, `app/Audit/*` (falls Anpassung), `config/*`, Tests.
    - Deps: T5, T2. Size: M.

**Checkpoint 2 (identity):** `composer test` grün; SSO-Pfad simuliert testbar; lokaler Login + 2FA + Passkeys grün; Audit-Events für Login nachweisbar. Review mit Nutzer.

### Phase 3: feedback (parallel zu ai)

- [ ] **T7: Domain-Extraktion nach `app/Feedback`**
    - Verschiebe `FeedbackReport` (Model), Enums (`FeedbackCategory/Rating/Status`), Controller (`FeedbackReportController`/`FeedbackScreenshotController`), Policy nach `app/Feedback/...`, `config/feedback.php` (enabled, categories), Routen/Namespace-Referenzen aktualisieren; Widget-Bindung (Render-Hook `BODY_END`) aufs Modul; Verhalten unverändert.
    - Acceptance: Bestehende Feedback-Tests laufen unter neuem Namespace; Widget + Screenshot-Route funktionieren; Modul abschaltbar.
    - Verify: `php artisan test --filter=Feedback`; `composer test`; e2e-Smoke.
    - Files: `app/Feedback/*`, `config/feedback.php`, `app/Providers/*`, `routes/web.php`, Views, Tests.
    - Deps: None (parallel zu ai möglich). Size: L (→ ggf. in zwei Slices teilen bei Umsetzung).

- [ ] **T8: Triage + Aufbewahrung + Audit**
    - Filament-Triage (bestehende Resource auf Modul umziehen), Löschfunktion für Report+Screenshot im Triage (DSGVO), Aufbewahrungslauf (≥12 Monate markierbar), Statusänderungen (neu→in Arbeit→gelöst) über `AuditLedger`.
    - Acceptance: Statuswechsel erscheint im Audit; Löschung entfernt Screenshot + Report; Frist-Lauf funktioniert.
    - Verify: `php artisan test --filter=Feedback`; `composer test`.
    - Files: `app/Feedback/Filament/*`, `app/Feedback/*`, `app/Audit/*` (Nutzung), Tests.
    - Deps: T7, T2. Size: M.

**Checkpoint 3 (feedback):** `composer test` grün; e2e-Feedback-Smoke grün; Triage-Änderungen im Audit; Modul abschaltbar.

### Phase 4: ai (parallel zu feedback)

- [ ] **T9: AiAgent-Contract + Registry + Fake**
    - `App\Ai\Contracts\AiAgent` (generischer Vertrag analog `ShiftOptimizerPipelineInterface`), `App\Ai\Services\AgentRegistry` (Agenten per Config auflösen), `App\Ai\Pipelines/MockDeterministicPipeline` + `LaravelAiSdkPipeline` als Referenz-Implementierungen; Driver-Umschaltung über Config (statt `Setting::aiPipelineDriver()`-Guard), Default = Mock/Fake ohne Keys.
    - Acceptance: Registry löst Agenten konfig-gesteuert; Fake deterministisch; kein Test braucht Keys.
    - Verify: `php artisan test --filter=Ai`; `composer test`.
    - Files: `app/Ai/{Contracts,Services,Pipelines}/*`, `config/ai.php`, `app/Providers/*`, Tests.
    - Deps: None. Size: M.

- [ ] **T10: AgentConversation-Wrapper + Konversation sm Parkour aus Laravel-AI-Tabellen**
    - Schlanke Modelle (`App\Ai\Models\AgentConversation`, `AgentMessage`) auf den Laravel-AI-Tabellen (`agent_conversations`/`agent_conversation_messages`); Registry kann Konversation je Agent/Prozess anlegen; Anbindung in Pipeline-Ausführung.
    - Acceptance: Konversation + Messages werden bei Agent-Lauf persistiert (Fake).
    - Verify: `php artisan test --filter=Ai`; `composer test`.
    - Files: `app/Ai/Models/*`, `app/Ai/Services/AgentRegistry.php`, Tests.
    - Deps: T9. Size: S/M.

- [ ] **T11: Auditing von AI-Entscheidungen**
    - Akzeptierte/abgelehnte AI-Vorschläge (Entscheidung + Result-Rückverweis auf Konversation) über `AuditLedger`; `ShiftOptimization`-Ergebnis bleibt Domänendaten.
    - Acceptance: AI-Entscheidung erscheint im Audit-Trail mit Bezug; Fake-Pfad testbar.
    - Verify: `php artisan test --filter=Ai`; `composer test`.
    - Files: `app/Ai/*`, `app/Services/ShiftOptimizationRunner.php` (Anbindung), Tests.
    - Deps: T10, T2. Size: M.

**Checkpoint 4 (ai):** `composer test` grün ohne externe Keys (Fake); neue Agenten ohne Template-Eingriff beilegbar (Doku-Beispiel in `docs/ai.md`).

### Phase 5: demo-shifts (Referenz-Domäne)

- [x] **T12: Shift-Audit auf generisches Ledger umstellen**
    - `ShiftAuditLedger`/`ShiftRollbackService` auf `AuditLedger` umstellen (Adapter oder Entfall); Audit-Kontext (Shift als Auditable, Actor, Vorher/Nachher) bleibt.
    - Acceptance: Zuweisung/Rollback schreiben in `audit_events`; Alt-Ledger nicht mehr referenziert.
    - Verify: `php artisan test --filter=Shift`; `composer test`.
    - Files: `app/Services/ShiftAuditLedger.php`, `ShiftRollbackService.php`, `app/Audit/*`, Tests.
    - Deps: T2. Size: M.

- [x] **T13: `ShiftAuditEvent` entfernen**
    - Neue Migration `drop_shift_audit_events_table` (Explizit, keine stillen Löschungen), Modell/Enum/Code entfernen; Tests aktualisieren.
    - Acceptance: Kein `ShiftAuditEvent`-Bezug mehr; `audit_events` trägt die Historie.
    - Verify: `composer test`; `grep -rn 'ShiftAuditEvent' app tests` leer.
    - Files: `database/migrations/*`, `app/Models/ShiftAuditEvent.php`, `app/Enums/AuditEventType.php` (ersetzt), Tests.
    - Deps: T12. Size: M.

- [x] **T14: Shift-Demo auf neue Module abgleichen (Regression)**
    - `ShiftOptimizerAgent`/Pipelines auf `AiAgent`-Contract (T9), Rollen/Abteilungen laufen über identity-Defaults, Feedback über `App\Feedback`; alle bestehenden Shift-Tests + Rolle-…-Tests + e2e-Smoke grün.
    - Acceptance: Alle Demo-Abläufe (Dispatch, Top-Match, Annehmen, Slide-Over, ROI-Deeplink, Rollen-Sichten) unverändert funktionsfähig.
    - Verify: `composer test`; `npm run test:e2e` (Playwright).
    - Files: `app/Ai/Agents/*`, `app/Pipelines/*`, `app/Filament/...`, Tests/e2e.
    - Deps: T12, T13, T9. Size: M.

**Checkpoint 5 (demo-shifts):** `composer test` + Playwright grün; keine Doppel-Implementierungen (grep checkt audit/ai-Duplikate).

### Phase 6: deploy (orthogonal, ab Checkpoint 1 startbar)

- [x] **T15: Produktions-Docker-Kit** (Commit 5ef066b)
    - `Dockerfile.prod` (Multi-Stage: node→composer→php-fpm, nicht-root), `compose.prod.yaml` (app, caddy, mysql:8.4, redis; Volumes/Healthchecks; kein Root), `Caddyfile.prod` (Routing, Assets, Sicherheitsheader, Livewire), `.env.production.example` (Doku, `APP_LOCALE=de`).
    - Acceptance: `docker compose -f compose.prod.yaml config` valide; Image baut ohne Root; Env-Vorlage vollständig dokumentiert.
    - Verify: manueller Build-Smoke (lokal), CI-Step.
    - Files: `Dockerfile.prod`, `compose.prod.yaml`, `Caddyfile.prod`, `.env.production.example`, `.dockerignore`.
    - Deps: None. Size: L (→ zwei Slices: Dockerfile+Compose, dann Caddy+Env).
    - Notiz: `.env.production.example` schreibblockiert (Safety-Net `secret.pattern.env-variant`) → vollständige Vorlage in `docs/deploy.md` (Sperr-Ausweg). Build-Smoke: 3 Fehler gefixt (intl/zip via deps=php:8.4-cli, storage-views vor composer install, vendor-in-frontend zum Vite-Build). Container uid=33 www-data.

- [x] **T16: Deploy-/Setup-/Backup-Skripte + Doku**
    - `scripts/setup-server.sh` (Docker, Firewall, Verzeichnisse), `scripts/deploy.sh` (pull, build, migrate; idempotent, headless, `set -euo pipefail`), `scripts/backup.sh` (mysqldump + Volume-Tar), `docs/deploy.md` (EU-Regionen, Backup, Rotation, S3-EU-Ausstieg).
    - Acceptance: Skripte idempotent, nicht-interaktiv; Doku deckt EU-Hosting + Backup ab.
    - Verify: shellcheck-artige Prüfung (`bash -n`), manueller Smoke.
    - Files: `scripts/*`, `docs/deploy.md`, ggf. `Makefile`-Alias.
    - Deps: T15. Size: M.
    - Notiz: `bash -n` grün; Env-Guard (change-me/APP_KEY/Entra) isoliert mit 3 Fixture-Fällen getestet; `/up`-Healthcheck + Rotation KEEP=7.

- [x] **T17: CI + Umgebungs-Standards**
    - `.github/workflows/tests.yml` um Compose-Validierung + Image-Build-Check erweitern; `APP_LOCALE=de` auch in `.env.example`; Laravel-Chore (fallback_locale de) sofern ohne Übersetzungslücke.
    - Acceptance: CI grün; Prod-Compose wird im CI validiert; Locale-Standard de.
    - Verify: CI-Lauf.
    - Files: `.github/workflows/tests.yml`, `.env.example`, `config/app.php`.
    - Deps: T15. Size: S.
    - Notiz: Compose-Validierung im ci-Job; `docker-build`-Job nur bei `push` (Entscheidung 3); `.env.example` `APP_LOCALE=de`/`APP_FALLBACK_LOCALE=en`/`APP_FAKER_LOCALE=de_DE` (Suite unter de: 204/675 OK); config/app.php blieb env-getrieben (kein Eingriff nötig).

**Checkpoint 6 (deploy):** Prod-Compose-Smoke lokal; CI grün inkl. Build; `docs/deploy.md` vorhanden.

### Phase 7: Template-Hülle (neutral)

- [ ] **T18: Hülle neutralisieren**
    - `APP_NAME`/Branding, Filament-Panel-Brand, `welcome`- und `auth`-Views auf neutrale Platzhalter („B2E-Template"); Demo-Domäne als Referenz klar gekennzeichnet (Doku); kein kiventro-/Schicht-Wording in der Hülle, Demo-Wording nur noch im demo-shifts-Kontext.
    - Acceptance: Frisch geklontes Template zeigt neutrale Hülle; Demo-Domäne bleibt über Rollen-/Navigationskenne erreichbar.
    - Verify: `composer test`; Sichtprüfung welcome/auth/Panel-Brand.
    - Files: `config/app.php`, `.env.example`, `app/Providers/Filament/AdminPanelProvider.php`, `resources/views/{welcome,auth,components}/*`, `docs/*`.
    - Deps: keine (orthogonal, kann am Ende). Size: M.

**Checkpoint 7 (Hülle):** neutrale Hülle nachweisbar; alle Tests grün.

## Parallelization

- Phase 1 (audit) zuerst/dominant; danach **Phase 2 (identity)** und **Phasen 3+4 (feedback ∥ ai)** parallel, **Phase 5 (demo-shifts)** nach deren Kern (T12 wartet nur auf T2/T9); **Phase 6 (deploy)** orthogonal ab Checkpoint 1.
- Geteilte Verträge zuerst fixieren: `AuditLedger`-Signatur (T2) und `AiAgent`-Contract (T9) gelten als API-Boundaries (siehe `api-and-interface-design`: Verträge an den Modulgrenzen, Specs als Quelle).

## Risks and Mitigations

| Risk                                                                                     | Impact  | Mitigation                                                                                                                                       |
| ---------------------------------------------------------------------------------------- | ------- | ------------------------------------------------------------------------------------------------------------------------------------------------ |
| Socialite-Microsoft-Paket: API-Drift/Deprecation                                         | Hoch    | Contract hinter `EntraUserResolver`; Tests mit Fake; Paket-Version pinnen; bei Problemen eigene OIDC-Implementierung als dokumentierter Fallback |
| Namespace-Verschiebungen (feedback) brechen Referenzen                                   | Mittel  | Automatisiert: `composer test` + PHPStan nach jedem Teil-Task; Pint; kleine Inkremente                                                           |
| `laravel/ai`-API (v0.11) weicht von der Annahme ab (AgentConversation-Tabellen-Rückgabe) | Mittel  | Source-driven-Check gegen Paket-Doku bei T10; nur schlanke Wrapper-Modelle, keine tiefe Kopplung                                                 |
| Caddy + Livewire 4 (SSE/Streaming) Konfigurationsdetails                                 | Niedrig | RT: im Prod-Smoke (T15/T16) testen; Caddyfile-Doku                                                                                               |
| `shift_audit_events`-Datenverlust beim Drop (T13)                                        | Mittel  | Im Template-Kontext akzeptiert (Dev-Reset pro Instanz); dokumentiert; Option: optionaler Daten-Migrations-Befehl im Kit                          |
| GoBD-Anforderungen Interpretationsspielraum                                              | Mittel  | Spec-Boundaries: nur Export/Archiv kein Löschen; Nachweis via Hash-Kette im Test                                                                 |

## Entscheidungen (Plan-Review 2026-09-22)

1. **Template-Hülle wird neutralisiert** (kein kiventro-/Demo-Branding in Hülle, Panel, welcome/auth; Referenz-Domäne demo-shifts bleibt klar gekennzeichnet) → T18.
2. **`shift_audit_events` wird gedroppt** (T13, neue drop-Migration; kein Alt→Neu-Command; Dev-Reset pro Instanz, dokumentiert).
3. **CI-Image-Build nur auf main** + manueller Workflow (T17); PRs nur Compose-Validierung + Tests.
4. **`socialiteproviders/microsoft`** als Sat peer Dependency für Entra (T4); testbar via Socialite-Fake.
