# Intent: B2E-App-Template

> Abgestimmt im Ideation-Workshop am 2026-09-22. Grundlage für Spezifikation und Planung.

## Auf einen Blick

Die Boutique baut aus der Schichtplanungs-Demo ein wiederverwendbares, Docker-basiertes B2E-Template (Laravel + Filament + Laravel AI SDK). Jeder Kunden-Prototyp entsteht als **eine eigene Instanz mit eigener Fachlogik** — aber auf einem gemeinsamen, complianten Fundament: Entra-ID, Rollen, GoBD-feste Auditierung, EU-Betrieb und eine nachgewiesen steckbare KI-Integration. Ziel: Der nächste Kunde startet nicht bei Null, und ein Prototyp ist in wenigen Tagen live.

## Problem Statement

**Wie könnten wir** die Schichtplanungs-Demo in ein wiederverwendbares, Docker-basiertes B2E-Template verwandeln, mit dem die Boutique den nächsten Kunden-Prototypen in wenigen Tagen ausrollt — DSGVO/GoBD-konform, Entra-ID-basiert, EU-gehostet —, ohne dass der Kunde bei Null startet?

## Kontext

- **Betrieb:** KI-Boutique, die Prozesse für Kunden automatisiert (Kundenprojekt-Arbeit).
- **Demo-App im Repo:** Schichtplanung (Shift Planning / Dispatch) — diente zur Ermittlung der Gemeinsamkeiten. Die Fachdomäne bleibt Beispiel und Erweiterungspunkt.
- **Compliance:** DSGVO, GoBD, EU-Datenresidenz → Docker-Deployment auf eigenen EU-Servern (kein Managed-SaaS, weil mit echten Kundendaten gearbeitet wird).
- **Zielgruppe:** deutsche Mittelständler.

## Recommended Direction

Fünf Säulen des Templates:

1. **Entra-ID als Identitätsquelle** — Referenz-Integration im Template via Socialite-OIDC; keine Neuentwicklung pro Kunde.
2. **Speed-to-Result** — Mitarbeitende sind schnell drin, erledigen ihre Aufgabe, sind schnell raus. Rollengetriebene Sichten statt Bürokratie.
3. **GoBD-first-Auditierbarkeit** — Organisationsprinzip, kein Feature: Forward-only-Ledger, keine Hard-Deletes, exportierbare Logs.
4. **Rollen** — vier Seed-Rollen mit Policy-Matrix, pro Kunde anpassbar.
5. **KI als erste-Klasse-Säule** — verallgemeinerte Mock/Live-Pipeline wird als Referenz-Integration mitgeliefert; „steckbar“ ist damit nachgewiesen, nicht nur versprochen.

Das Template liefert vier Artefakte:

- **Scaffold:** Filament-Panel, Rollen, Entra-Adapter (Socialite-OIDC), `Auditable`-Concern, EU-Produktions-Compose.
- **Referenz-Vertical:** Schichtplanung bleibt als anklickbarer Durchlauf (Seed-Daten, Demo-Login). Der nächste Kunde tauscht die Fachlogik, nicht das Fundament.
- **Rollout-Playbook:** EU-Provisioning, Entra-Registrierung, DSGVO-Checkliste, Backup/Restore, Audit-Export — hoster-agnostisch angelegt (Referenz-Hoster noch offen).
- **In-App-Feedback-Paket:** Widget + Triage aus der Demo als Lieferanteil des Templates (kein eigener Pfeiler).

## Key Assumptions to Validate

- [ ] **Gemeinsamkeits-Hypothese:** Die fünf Säulen gelten auch für den nächsten Kunden — testen, indem der nächste Prototyp auf dem Template entsteht (nicht daneben).
- [ ] Entra ID ist die reale Identitätsquelle der Zielkunden — mit den ersten zwei Pilotkunden verifizieren.
- [ ] Einzelinstanz pro Kunde ist bepreisbar; Betriebskosten ≠ Anlaufkosten („ohne DevOps-Aufwand“ bezieht sich auf den Anlauf, nicht auf den Dauerbetrieb).
- [ ] „In wenigen Tagen live“ ist einhaltbar — Trockenlauf auf einer Sandbox vor dem ersten Kunden.
- [ ] Das Template wird erprobt (Dogfooding): erster Kunden-Prototyp baut auf dem Template.

## MVP Scope

- Scaffold: Filament-Panel, vier Rollen, Entra-Adapter, `Auditable`-Concern, EU-Produktions-Compose
- Referenz-Vertical Schichtplanung mit Seed-Daten und Demo-Login
- Generalisierte Referenz-KI-Integration (Mock/Live)
- Rollout-Playbook v1 (hoster-agnostisch)
- In-App-Feedback-Paket (Widget + Triage)

## Not Doing (and Why)

- **Multi-Tenant / SaaS-Vermietung** — bewusst Einzelinstanz: echte Kundendaten + GoBD.
- **B2C** — Zielgruppe ist der deutsche Mittelstand.
- **Konkrete kundenspezifische KI-Use-Cases** — das ist Kundenprojekt-Arbeit; das Template liefert den steckbaren Pfad samt Referenznachweis.
- **Generische Fachlogik-Abstraktionen** — entstehen erst beim zweiten Kunden (YAGNI), nicht vorab.
- **Volles DevOps-Paket (Monitoring, Alerting, CI/CD-Kanon)** — v1 nur Playbook mit Checklisten.

## Open Questions

- Welcher EU-Hoster wird im konkreten Projekt Referenz (Hetzner, IONOS, …)? Das Playbook bleibt bis dahin hoster-agnostisch.
- Konkrete Zeitvorgabe: „wenige Tage“ wird nach dem ersten Trockenlauf mit einer Zahl belegt.
