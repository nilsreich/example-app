# Spec: demo-shifts

Modul-Id: `demo-shifts` (Capability Map: B2E-App-Template). Freigabestatus: **freigegeben (2026-09-22)**.

## Objective

Die Schichtplanung bleibt als **Referenz-Domäne** im Template: Sie demonstriert, wie ein Kundenprojekt auf den Template-Modulen aufbaut, und hält die Demo-Abläufe (Dispatch, Top-Match, Annehmen, Slide-Over, ROI-Deeplink, Rollen-Sichten) funktionsfähig. Sie wird dazu an die generalisierten Module angebunden statt eigener Insellösungen:

- **`audit`**: Zuweisungsänderungen laufen über das generische `AuditLedger` (Cloud-Migration weg von `ShiftAuditEvent`/`ShiftAuditLedger`; Ledger-Semantik bleibt, Implementierung wird das Modul).
- **`identity`**: Rollen-/Abteilungs-Scope bleibt wie gebaut (`UserRole`, `visibleDepartments`, Policies); zusätzlich nutzbar mit Entra-Mapping (kein Demo-Zwang).
- **`ai`**: `ShiftOptimizerAgent` + `LaravelAiSdkPipeline`/`MockDeterministicPipeline` werden auf den neuen Erweiterungspunkt (`AiAgent`, Registry) umgestellt; Berechnen-Action bleibt.
- **`feedback`**: Widget/Triage verwenden das `App\Feedback`-Modul.
- Verhalten/Verzeichnisse: Der Demo-Domänencode (Models, Services unter `App\Services`, Filament-Resource) bleibt funktional; wo sinnvoll, klar gekapselt (`App\`-Bereiche beibehalten, keine Doppel-Implementierungen).

## Tech Stack

Unverändert: PHP 8.3, Laravel 13, Filament 5, Livewire 4/Flux, laravel/ai, MySQL/SQLite + Redis; Playwright e2e, PHPStan, Pint.

## Commands

```
composer test
php artisan test --filter=Shift
npx playwright test tests/e2e/shifts.spec.ts   # Referenz-Smoke
```

## Project Structure

Bestehende Struktur bleibt (Models `App\Models\Shift*`, Services, `app/Ai/Agents/ShiftOptimizerAgent.php`, Filament-Resources, Policies), mit Umbauten:

```
app/Models/Shift…                       # unverändert, ggf. Auditable-Trait statt ShiftAuditEvent-Pfad
app/Services/ShiftAuditLedger.php       # → dünner Adapter auf AuditLedger oder ersatzlos entfernt
app/Ai/Agents/ShiftOptimizerAgent.php   # implementiert AiAgent-Contract (Registry)
tests/Feature/Shift/…                   # Migrations-/Verhaltenstests bleiben, angepasst
```

## Code Style

Wie bisher (Pint/PHPStan; deutsche UI, englischer Code, deutsche Commits). Konsistenz: keine neuen Kopfimplementierungen für audit/ai/feedback im Demo-Code.

## Testing Strategy

- Bestehende Shift-Tests (Zuweisung, Top-Match, Rollback, ROI, Rollen-Scope) bleiben als Regression + werden auf generisches Audit umgestellt.
- Neu: Test, dass AI-Entscheidungen im `audit`-Trail erscheinen; Fixtures deterministisch (Fake-Pipeline).

## Boundaries

- **Always:** `composer test`; keine direkten Eloquent-Writes an `audit_events`; keine echten AI-Provider in Tests.
- **Ask first:** Umbau von Shift-Services über den Umfang der Module hinaus; Entfernen von Demo-Features (ROI-Deeplink, Slide-Over).
- **Never:** Doppelte Audit-/AI-/Feedback-Implementierungen neben den Modulen; Verhaltensänderungen an bestehenden Demo-Abläufen ohne Test.

## Success Criteria

- [ ] Shift-Demo-Abläufe funktionieren unverändert (Regression grün) und nutzen generisches audit/ai/feedback/identity.
- [ ] `composer test` grün; e2e-Shift-Smoke grün.
- [ ] Keine Doppel-Implementierungen; `ShiftAuditEvent`-Pfad ersetzt (oder dokumentiert als Demo-only-Alternative — Entscheidung in Planung).

## Entscheidungen (Freigabe 2026-09-22)

- **`ShiftAuditEvent`** (Modell, Enum, Tabelle/Migration) wird nach der Migration auf das generische Ledger **entfernt**; das generische Modul wird der Referenzpfad.
- **Rollen-/Abteilungs-Logik**: Default-Rollen-Set wandert ins `identity`-Modul (siehe SPEC-identity); die Shift-Demo nutzt es und bleibt kundenspezifisch anpassbar.
