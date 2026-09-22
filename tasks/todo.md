# Tasks: B2E-App-Template

> Quelle: `tasks/plan.md` (Review-Gate offen). Tasks werden bei Freigabe abgearbeitet. Reihenfolge/Deps im Plan.

## Phase 1: audit (Foundation)

- [x] T1: audit_events-Schema + Hash-Kette
    - Acceptance: Kette gültig; Einzeländerung bricht Kette; nur Write-Pfad
    - Verify: `php artisan test --filter=Audit`; `composer test`
- [x] T2: AuditLedger + Auditable-Trait + Append-only-Guard
    - Acceptance: Ledger schreibt Ketten-korrekt; Rollback verkettet; Update/Delete scheitert; Query-Filter greifen
    - Verify: `php artisan test --filter=Audit`; `composer test`
- [x] T3: Audit-Export (CSV/JSON) + Filament-Ansicht
    - Acceptance: Export policy-geschützt, Formate korrekt, Filter greifen
    - Verify: `php artisan test --filter=Audit`; `composer test`

**Checkpoint 1 (audit):** `composer test` grün; Kettenbruch nachweisbar; Review mit Nutzer.

## Phase 2: identity

- [x] T4: Entra-SSO-Grundgerüst (config/entra.php, Socialite-Microsoft, Routen, Controller)
    - Acceptance: Redirect/Callback-Route erreichbar; lokaler Login unverändert
    - Verify: `php artisan test --filter=Identity`; `composer test`
- [x] T5: Provisioning + Mapping (entra_object_id, Resolver, GroupRoleMapper)
    - Acceptance: Neu-Provisioning, Email-Wechsel-Match, Ablehnung ohne Berechtigung
    - Verify: `php artisan test --filter=Identity` (Socialite-Fake); `composer test`
- [x] T6: Audit-Anbindung + Hybrid-Absicherung + Rollen-Default-Set
    - Acceptance: SSO-Events im Audit; lokaler Login/2FA/Passkeys grün
    - Verify: `php artisan test --filter=Identity`; `composer test`

**Checkpoint 2 (identity):** SSO-Pfad simuliert testbar; Hybrid-Login grün; Audit-Events nachweisbar; Review.

## Phase 3: feedback

- [x] T7: Domain-Extraktion nach app/Feedback + config/feedback.php
    - Acceptance: Bestehende Feedback-Funktion unter neuem Namespace; abschaltbar
    - Verify: `php artisan test --filter=Feedback`; `composer test`
- [x] T8: Triage-Löschfunktion + Aufbewahrung (12 Mo) + Status-Änderungen ins Audit
    - Acceptance: Statuswechsel im Audit; Löschung entfernt Report+Screenshot; Fristlauf testbar
    - Verify: `php artisan test --filter=Feedback`; `composer test`

**Checkpoint 3 (feedback):** e2e-Smoke grün; Triage im Audit; abschaltbar.

## Phase 4: ai

- [x] T9: AiAgent-Contract + AgentRegistry + Fake (Driver über Config)
    - Acceptance: Registry konfig-gesteuert; Fake deterministisch; keine Keys in Tests
    - Verify: `php artisan test --filter=Ai`; `composer test`
- [x] T10: AgentConversation-Wrapper (Laravel-AI-Tabellen) + Persistenz
    - Acceptance: Konversation + Messages persistiert (Fake)
    - Verify: `php artisan test --filter=Ai`; `composer test`
- [x] T11: AI-Entscheidungen im Audit (akzeptiert/abgelehnt, Result-Bezug)
    - Acceptance: AI-Entscheidung + Konversations-Bezug im Audit-Trail
    - Verify: `php artisan test --filter=Ai`; `composer test`

**Checkpoint 4 (ai):** grün ohne Keys; Agenten ohne Template-Eingriff (docs/ai.md).

## Phase 5: demo-shifts (Referenz)

- [ ] T12: Shift-Audit auf generisches Ledger (Adapter/Entfall)
    - Acceptance: Zuweisung/Rollback → audit_events; Alt-Ledger unbenutzt
    - Verify: `php artisan test --filter=Shift`; `composer test`
- [ ] T13: ShiftAuditEvent entfernen (drop-Migration, Code weg)
    - Acceptance: kein Bezug mehr; audit_events trägt Historie
    - Verify: `composer test`; `grep -rn ShiftAuditEvent app tests` leer
- [ ] T14: Shift-Demo auf neue Module (Regression + e2e)
    - Acceptance: alle Demo-Abläufe unverändert funktionsfähig
    - Verify: `composer test`; `npm run test:e2e`

**Checkpoint 5 (demo-shifts):** Playwright grün; keine Doppel-Implementierungen.

## Phase 6: deploy (orthogonal)

- [ ] T15: Dockerfile.prod + compose.prod.yaml + Caddyfile.prod + .env.production.example
    - Acceptance: compose config valide; Image-Build nicht-root
    - Verify: `docker compose -f compose.prod.yaml config`; lokaler Build-Smoke
- [ ] T16: setup-server.sh + deploy.sh + backup.sh + docs/deploy.md
    - Acceptance: idempotent, headless; Doku EU/Backup/Rotation
    - Verify: `bash -n scripts/*`; manueller Smoke
- [ ] T17: CI-Erweiterung (Compose-Validierung, Image-Build) + APP_LOCALE=de
    - Acceptance: CI grün inkl. Build-Check; Locale-Standard de
    - Verify: CI-Lauf

**Checkpoint 6 (deploy):** Prod-Compose-Smoke; CI grün; docs/deploy.md vorhanden.

## Phase 7: Template-Hülle (neutral)

- [ ] T18: Hülle neutralisieren (APP_NAME/Branding, Panel-Brand, welcome/auth-Views neutral; Demo als Referenz markiert)
    - Acceptance: frische Instanz = neutrale Hülle; Demo-Domäne erreichbar gekennzeichnet
    - Verify: `composer test`; Sichtprüfung

**Checkpoint 7 (Hülle):** neutrale Hülle; alle Tests grün.

---

## Entscheidungen (Plan-Review 2026-09-22)

1. Template-Hülle neutralisieren (T18)
2. shift_audit_events droppen (T13)
3. CI-Image-Build nur main (T17)
4. socialiteproviders/microsoft als Entra-Abdeckung (T4)
