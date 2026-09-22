# Spec: audit

Modul-Id: `audit` (Capability Map: B2E-App-Template). Freigabestatus: **freigegeben (2026-09-22)**.

## Objective

Generisches, GoBD-taugliches Audit-Trail als einzige Quelle für alle Fach- und Systemmodule (identity, demo-shifts, ai). Konzept ist die Generalisierung des vorhandenen Demo-Ledgers (`ShiftAuditEvent`/`ShiftAuditLedger`):

- **Append-only**: Events werden nur angehängt, niemals geändert oder gelöscht (immutable, `created_at` ohne `updated_at`; DB-Trigger/Lock zusätzlich gegen direkte Manipulation).
- **Hash-Kette**: jeder Eintrag trägt `prev_hash` und `hash = hash(prev_hash ‖ kanonischer Inhalt)` → nachträgliche Änderung ist erkennbar; jede Lücke bricht die Kette.
- **Wer/Wann/Was/Vorher/Nachher**: `actor_user_id`, `created_at`, `event_type`, `previous_state`/`new_state` (json), versioniert; Rollback zeichnet ein neues, verkettetes Event mit Bezug aufs stornierte.
- Erfassung von Web-Client (IP/User-Agent) und System-Context (Source: Web | AI | CLI), damit AI-Entscheidungen nachvollziehbar sind.

## Tech Stack

- Laravel 13 (Eloquent), MySQL 8.4 + Redis (Prod), SQLite (Dev-Tests)
- Kein Drittanbieter-Audit-Paket (in-house, leichtgewichtig — freigegebene Annahme)

## Commands

```
composer test
php artisan test --filter=Audit
```

## Project Structure

```
app/Audit/
  Models/AuditEvent.php            # morph auditable, immutable
  AuditLedger.php                  # record(), rollback(), query()-API
  Concerns/Auditable.php           # Trait für Domänen-Modelle
  Enums/AuditEventType.php         # verallgemeinertes enum (vorhandenes wird ersetzt)
  Http/Controllers/AuditExportController.php  # CSV/JSON-Export (GoBD-Archiv), policy-geschützt
database/migrations/xxxx_create_audit_events_table.php
tests/Feature/Audit/AuditLedgerTest.php
tests/Unit/Audit/HashChainTest.php
```

Das vorhandene `ShiftAuditEvent`-Modell/Enum bleibt im Demo-Domänencode, bis `demo-shifts` auf das generische Modul migriert (dann entfällt/ersetzt es — siehe SPEC-demo-shifts).

## Code Style

Wie Repo (Pint, PHPStan); Hash-Berechnung als kleine, pure, getestete Funktion (`sha256` über kanonisches JSON, kein Zufall). UI-Texte (Export, Ansicht) Deutsch.

## Testing Strategy

- Unit: Hash-Kette — Reihenfolge gültig, einzelne Änderung = Kettenbruch; kanonische Serialisierung stabil.
- Feature: `AuditLedger::record` append-only (Update/Delete-Versuche schlagen fehl oder werden verhindert), Rollback erzeugt verkettetes Folge-Event, Filtern nach Actor/Auditable/Zeitraum, Export (CSV/JSON) korrekt + policygeschützt.
- Performance-Smoke: Massen-Insert (z. B. 10k Events) bleibt unter Grenzwert (Index-Plan).

## Boundaries

- **Always:** `composer test`; Events nur über `AuditLedger` schreiben (kein direktes Eloquent-save in Fachcode); Export nur für audit-berechtigte Rollen.
- **Ask first:** Schema-Änderungen an `audit_events`, zusätzliche Aufbewahrungs-/Löschlogik, Performance-Indizes.
- **Never:** Events aktualisieren/löschen; Hash-Algorithmus oder Serialisierung wechseln ohne Rückwärts-Kompatibilität; Audit-Daten in öffentliche Backups ohne Schutz.

## Success Criteria

- [ ] Generische `audit_events`-Tabelle + `AuditLedger`; `Auditable`-Trait für Fachmodelle.
- [ ] Hash-Kette aktiv: Manipulation eines Eintrags bricht die Kette (Test beweist es).
- [ ] Rollback erzeugt verkettetes Folge-Event (nichts wird überschrieben).
- [ ] `identity`- und `demo-shifts`-Events laufen über das Modul (demo-shifts migriert vom ShiftAuditEvent).
- [ ] Policy-geschützter Export (CSV/JSON) vorhanden.
- [ ] `composer test` grün.

## Entscheidungen (Freigabe 2026-09-22)

- **Aufbewahrung**: keine aktive Löschung; Archivierung über den policy-geschützten Export. Kein separater Archiv-Mechanismus zu Beginn.
- **Append-only-Erzwingung** app-seitig (Model-Guard, kein Update/Delete-Pfad im Code); kein separater DB-Rollen-/Service-Account-Mechanismus zu Beginn.
