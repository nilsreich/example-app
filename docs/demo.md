# Demo-Referenz: Schichtplanung (demo-shifts)

> Die **Hülle** des Templates ist bewusst neutral (App-Name „B2E-Template",
> Login/Welcome ohne Demo-Branding). Die Schichtplanung ist die **Referenz-Domäne**
> (siehe `CAPABILITY-MAP.md` → `demo-shifts`), die alle Module — Identität,
> Audit-Ledger, In-App-Feedback, KI-Erweiterungspunkt — an einem durchgängigen
> Beispiel durchspielt. Sie ist als Demo klar gekennzeichnet und bleibt über
> Rollen- und Navigationswissen erreichbar.

## Was die Demos zeigen

Szenario: kurzfristiger Personalausfall in der Logistik. Kritisch unbesetzte
Schichten warten auf KI-gestützte Umplanung — Ersatzvorschläge, 1-Klick-Zuweisung,
Forward-Rollback und ROI-Auswertung. Jede Änderung landet revisionssicher im
Audit-Ledger.

## Erreichbarkeit (Starten der Referenz-Domäne)

1. **Admin-Panel** läuft unter `/admin` (Login-Seite ist neutral, ohne Demo-Hinweis).
2. **Demo laden** (ein Login je Rolle, Web-Admin-Schalter „Demo-Szenario neu laden"
   auf dem Dashboard oder per Artisan):

    ```bash
    php artisan db:seed-kiventro-demo            # Basis-Szenario (Schichten + Benutzer)
    php artisan db:seed-kiventro-demo --with-history   # zusätzlich historische Schichten + Audit-Events
    ```

3. **Zugänge** (Passwort jeweils `kiventro-demo`):

    | E-Mail                         | Rolle                    | Fokus            |
    | ------------------------------ | ------------------------ | ---------------- |
    | `admin@kiventro.de`            | Web-Admin                | System & Triage  |
    | `gf@kiventro.de`               | Geschäftsführung         | ROI zuerst       |
    | `leitung.logistik@kiventro.de` | Bereichsleitung Logistik | eigene Abteilung |
    | `mitarbeiter@kiventro.de`      | Mitarbeiter              | Self-Service     |

4. **Wichtige Pfade** (nach Login): Dashboard-Cockpit (`/`), Schichten
   (`/admin/shifts`), Meine Schichten (`/admin/my-shifts`), ROI-Dashboard
   (`/admin`), KI-Pipeline-Modus (`/admin/pipeline-settings`), Audit-Log
   (`/admin/audit-log`).

## Abgrenzung: Hülle ↔ Referenz-Domäne

- **Neutral (Hülle):** `APP_NAME`, Welcome-Seite, Login-Seite, Panel-Brand.
- **Demo (klar markiert):** Dashboard-Cockpit inkl. KPI-Karten und
  „Demo-Szenario"-Card, Schicht-Ressourcen, Widgets, Seeder, Rollen-Leitfaden.

## Hinweise für die Weiterentwicklung

- Ein neuer Kunde startet mit der neutralen Hülle; die Referenz-Domäne kann als
  Vorlage dienen oder entfernt werden (`Shift`/`Employee`/`ShiftOptimization`-Module,
  `SeedKiventroDemo`, zugehörige Ressourcen/Widgets).
- Keep the shell clean: kein kundenspezifisches/kiventro-Wording in Hülle, Panel,
  Welcome- oder Auth-Views — Demo-Wording gehört ausschließlich in den
  demo-shifts-Kontext.
