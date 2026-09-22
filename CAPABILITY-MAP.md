# Capability Map: B2E-App-Template

Freigegeben: 2026-09-22. Index für die Modul-Specs (`SPEC-*.md`).

| Modul-Id      | Verantwortung                                                                                             | Hängt ab von                           | Status                          |
| ------------- | --------------------------------------------------------------------------------------------------------- | -------------------------------------- | ------------------------------- |
| `identity`    | Entra-ID-SSO, Benutzerkonten, 2FA/Passkeys (besteht), Rollen+Abteilungs-Scope aus Entra-Gruppen/Claims    | —                                      | Demo-Vorarbeit, Entra fehlt     |
| `audit`       | GoBD-taugliches Audit-Trail: generisch, append-only, revisionssicher (Wer/Wann/Was/Vorher-Nachher)        | `identity`                             | nur domänenspezifisch vorhanden |
| `feedback`    | In-App-Feedback (Widget + Element-Picker + Screenshot)                                                    | `identity`                             | in Demo fertig → extrahieren    |
| `ai`          | Laravel-AI-SDK-Erweiterungspunkt (Provider-Abstraktion, Agent-Konversationen), Demo-Use-Case als Beispiel | `identity`                             | Basis, Erweiterungspunkt fehlt  |
| `deploy`      | Produktions-Docker-Kit (EU): Dockerfile, Compose prod, Env-Vorlagen, Ein-Befehl-Deploy + CI               | — (infrastrukturell, parallel möglich) | fehlt                           |
| `demo-shifts` | Schichtplanung als Referenz-Domäne, die alle Module durchspielt                                           | `identity`, `audit`, `ai`              | in Demo vorhanden → anpassen    |

Build-Reihenfolge: `identity` → `audit` → (`feedback` ‖ `ai`) → `demo-shifts` als Referenz; `deploy` läuft orthogonal dazu.

## Globale Annahmen (freigegeben)

1. Das Demo-Repo ist der Same — das Template entsteht durch Generalisierung; Schichtplanung bleibt als Referenz-Domäne.
2. Entra ID per Laravel-Socialite (Microsoft-Provider); Rollen/Abteilungen mappen aus Entra-Gruppen/Claims auf die bestehende Rollen-Logik.
3. Audit in-house, leichtgewichtig (append-only-Tabelle + Service), kein Drittanbieter-Paket.
4. Deploy = eigene Produktions-Compose (kein Sail) auf EU-Servern; Sail bleibt für Entwicklung.
5. DB: MySQL 8.4 + Redis.
6. UI/App-Sprache Deutsch; Code/Kommentare Englisch; Commits Deutsch (wie bisher).
7. AI-SDK-Provider konfigurierbar; kein AI-Use-Case fix verdrahtet. Deutsche Mittelständler, DSGVO/GoBD, EU-Datenresidenz, keine Multi-Tenancy.
