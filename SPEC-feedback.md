# Spec: feedback

Modul-Id: `feedback` (Capability Map: B2E-App-Template). Freigabestatus: **freigegeben (2026-09-22)**.

## Objective

In-App-Feedback als wiederverwendbares Template-Modul (Marker.io-Stil, aber komplett lokal/DSGVO-konform). Aus der Demo extrahiert und generalisiert:

- Floating-Widget mit Kategorie-Auswahl, Freitext, **Element-Picker** (ausgewähltes Element → Selektor + Kontext), optionaler **Screenshot** (privat, nur über policy-geschützte Route ausgeliefert, nie direkt aus dem Storage).
- Kontext automatisch: Seiten-URL/-Titel, Browser-Info, User.
- Triage in Filament: offene Meldungen, Status (offen→in Arbeit→gelöst), Lösungsnotiz; Kategorien konfigurierbar.
- Entgegennahme gedrosselt (`throttle`), Berechtigungen über Policy; Screenshots unterliegen Aufbewahrungs-/Löschregeln (DSGVO).
- Modul abschaltbar/umschaltbar pro Kundeninstanz (Config), damit es nicht ungewollt im Endprodukt erscheint.

Bestehender Demo-Code (`FeedbackReport`, Controller, Policy, Route, Enums) wird unter das Modul-Namespace `App\Feedback` überführt; Tabellen/Migrationen werden übernommen, ggf. mit fehlenden Spalten.

## Tech Stack

- Laravel 13, Livewire 4 / Flux-Widget, Filament 5 (Triage-Resource), Storage `local` (private)
- Bestehende Drosselung + Policy bleiben

## Commands

```
composer test
php artisan test --filter=Feedback
npx playwright test              # Smoke: Widget öffnen, Meldung anlegen, Screenshot
```

## Project Structure

```
app/Feedback/
  Models/FeedbackReport.php
  Enums/FeedbackCategory.php, FeedbackStatus.php, (FeedbackRating.php)
  Http/Controllers/FeedbackReportController.php, FeedbackScreenshotController.php
  Policies/FeedbackReportPolicy.php
  Livewire/FeedbackWidget.php    # Floating-Widget (zuvor in Demo-Struktur)
  Filament/Resources/FeedbackReportResource.php   # Triage
  config/feedback.php            # aktiviert, Kategorien, Widget-Optionen
resources/views/feedback/...
tests/Feature/Feedback/...
tests/e2e/feedback.spec.ts
```

## Code Style

Wie Repo; Widget-UI Deutsch; Screenshot-Handling ohne Server-Side-Erpressung von Fremd-Domains (nur aktive Seite). Keine Tracking-/Analytics-Abhängigkeiten.

## Testing Strategy

- Feature: Meldung anlegen (mit/ohne Element-Picker/Screenshot), Throttle greift, Policy (nur berechtigte Rollen sehen/verwalten, Screenshot nur für eigenen Report bzw. Admin), Status-Updates mit Notiz, Löschung beachtet DSGVO-Rechte.
- Unit: Kontext-Build, Kategorien-Konfiguration.
- E2E: Widget öffnen, Meldung senden, Screenshot-Pfad ≤01 gesetzt.

## Boundaries

- **Always:** `composer test`; Throttle auf store; Screenshot-Auslieferung nur über geschützte Route.
- **Ask first:** Änderungen an `feedback_reports`-Schema; externe Feedback-Backends (z. B. Slack/Linear-Forwarding); Aufbewahrungsfristen.
- **Never:** Screenshots öffentlich via `storage:link`; personenbezogene Daten ungeprüft exportieren (DSGVO-Auskunft/Löschung muss möglich bleiben).

## Success Criteria

- [ ] Demo-Feedback funktioniert nach Extraktion unverändert (Widget, Picker, Screenshot, Triage).
- [ ] Modul über `config/feedback.php` abschaltbar; Namespace `App\Feedback`.
- [ ] Policy-geschützter Zugriff inkl. Screenshot-Route; Throttle aktiv.
- [ ] `composer test` + e2e-Smoke grün.

## Entscheidungen (Freigabe 2026-09-22)

- **Triage-Statusänderungen** (öffnen/bearbeiten/lösen) werden über das `audit`-Modul protokolliert.
- **Screenshot-Aufbewahrung**: 12 Monate; dazu eine manuelle Löschfunktion im Triage (DSGVO).
