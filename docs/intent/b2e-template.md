# Intent: B2E-App-Template

> Bestätigt im Interview am 2026-09-22. Grundlage für Spezifikation und Planung.

## Statement of Intent

- **Outcome:** Ein wiederverwendbares, Docker-basiertes B2E-Template
  (Laravel + Filament + Laravel AI SDK) als Startpunkt für Kunden-Prototypen —
  eine Instanz pro Kunde, eigene Fachlogik.
- **User:** (a) Mitarbeitende beim Kunden: schnell rein, Aufgabe erledigt,
  wieder raus. (b) Wir als Boutique: schneller Rollout ohne Neuentwicklung
  und ohne DevOps-Aufwand pro Kunde.
- **Why now:** Die Schichtplanungs-Demo hat als erster Durchlauf die
  Gemeinsamkeiten sichtbar gemacht; daraus soll jetzt ein Template entstehen,
  damit der nächste Kunde nicht bei Null startet.
- **Success:** Ein neuer Kunden-Prototyp ist in wenigen Tagen live:
  Entra-ID, Rollen, Audit, In-App-Feedback, EU-Docker-Deploy,
  AI-Fähigkeiten als steckbarer Erweiterungspunkt.
  (Konkrete Zeitvorgabe ergänzbar.)
- **Constraint:** Docker, EU-Hosting, DSGVO + GoBD-konform, kein Multi-Tenant,
  Entra ID als Identitätsquelle, Zielgruppe deutsche Mittelständler.
- **Out of scope:** Keine B2C-Anforderungen, keine Multi-Tenant-/SaaS-Vermietung,
  keine konkreten AI-Use-Cases (nur als Erweiterungspunkt), keine
  kundenspezifische Fachlogik (das ist Kundenprojekt-Arbeit).

## Kontext

- Betrieb: KI-Boutique, die Prozesse für Kunden automatisiert.
- Demo-App im Repo: Schichtplanung (Shift Planning / Dispatch) — diente zur
  Ermittlung der Gemeinsamkeiten. Fachdomäne bleibt Beispiel/Erweiterung.
- Vier Säulen des Templates: Entra-ID-Integration, Speed-to-Result
  (kurze Verweildauer bis Ergebnis), Auditierbarkeit (GoBD), Rollen.
- Compliance: DSGVO, GoBD, EU-Datenresidenz → Docker-Deployment auf eigenen
  EU-Servern (kein Managed-SaaS, weil mit echten Kundendaten gearbeitet wird).
