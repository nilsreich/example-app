# Spec: identity

Modul-Id: `identity` (Capability Map: B2E-App-Template). Freigabestatus: **freigegeben (2026-09-22)**.

## Objective

Entra-ID-SSO für Mitarbeiter (ein Microsoft-Mandant pro Instanz, keine Multi-Tenancy) mit **hybridem Login**: lokale Passwort-Konten bleiben als Fallback aktiv (Fortify + 2FA + Passkeys existieren bereits). Rollen (`UserRole`) und Abteilung (`department`) werden beim SSO-Login über eine konfigurierbare Mapping-Tabelle aus Entra-Gruppen-Object-IDs abgeleitet.

Abgedeckte Abläufe:

1. `GET /auth/entra` → Redirect zu Microsoft Entra (OAuth2/OpenID via Socialite, Microsoft-Provider).
2. Callback: User wird provisioniert (neu: Email/Name aus Tokens; Bestand: Match über Entra-Object-Id `sub`/`oid`), Rolle + Abteilung per Gruppen-Mapping gesetzt.
3. Lokaler Login (Passwort/2FA/Passkeys) bleibt parallel funktionsfähig (Fallback + Break-Glass + Dev/Test ohne Entra).
4. SSO-Events (Login/Logout/Provisioning/Fehler) werden über das `audit`-Modul protokolliert.
5. Nicht-Matching-Entra-Konten ohne gültige Gruppe + ohne Fallback dürfen nicht ins Panel (kein Default-Rollen-Verfall).

## Tech Stack

- PHP ^8.3, Laravel ^13.17, Filament ^5.0 (lokale Auth-Seite bleibt bestehen)
- laravel/fortify (vorhanden), laravel/socialite + `SocialiteProviders/Microsoft` (neu)
- Neu: `config/entra.php` (Tenant, Client, Group→Rolle/Abteilung-Mapping)

## Commands

```
composer test                        # lint + phpstan + phpunit (Gesamt-Gate)
php artisan test --filter=Identity
npx playwright test                 # e2e (Login-Erlaubnis/nur Smoke; Entra simuliert)
```

## Project Structure

```
app/Identity/
  EntraUserResolver.php             # Bereitstellen/Matchen des Users aus Entra-Attributen
  EntraGroupRoleMapper.php          # Mapping Group-ObjectId -> role | department
  Http/Controllers/EntraAuthController.php   # redirect + callback
config/entra.php                    # Tenant, Client-ID/Secret (env), Mapping-Tabellen
routes/entra.php                    # mit auth-Kontext, rate-limited
tests/Feature/Identity/EntraLoginTest.php
tests/Unit/Identity/EntraGroupRoleMapperTest.php
```

Bestehende User-Logik (`App\Models\User`, Rollen-Enum) bleibt erhalten und wird nur erweitert (keine Migration der User-Tabelle nötig, außer optionaler `entra_object_id`).

## Code Style

Konstruktor-Injection, `readonly` für stateless Services, volle Typisierung, keine `env()`-Aufrufe im Code (nur Config). Pint + PHPStan (Konfiguration wie Repo). UI-Texte Deutsch, Codewörter Englisch, Commit-Messages Deutsch.

## Testing Strategy

- Unit: Mapping-Tabellen (Gruppe→Rolle, Gruppe→Abteilung, Default/leer).
- Feature: Socialite-Fake — Neu-Provisioning, Bestand-Match, korrektes Mapping, Ablehnung ohne Berechtigung; lokaler Login weiter grün.
- E2E: nur Smoke mit simuliertem Entra (kein realer Test-Tenant im CI nötig).
- Kein Test braucht echte Credentials/Netzwerk.

## Boundaries

- **Always:** `composer test` vor Commit; Mapping ausschließlich in Config; Secrets nur via ENV.
- **Ask first:** neue Spalte auf `users` (z. B. `entra_object_id`), zusätzliche OAuth-Provider, Änderungen an Filament-Auth.
- **Never:** Client-Secret/App-Registrierungs-Keys im VCS; persistierte Tokens; SSO gegen mehrere Mandanten in einer Instanz.

## Success Criteria

- [ ] Hybrider Login: Entra-SSO und lokaler Passwort-Login funktionieren parallel; 2FA/Passkeys-Fallback unverändert.
- [ ] Neuer Entra-User bekommt korrekt gemappte Rolle/Abteilung (config-gesteuert), kein Default-Rollen-Verfall.
- [ ] Login/Provisioning-/Fehler-Events erscheinen im `audit`-Trail.
- [ ] `composer test` grün (inkl. Socialite-Fake-Tests, ohne echte Entra-Credentials).
- [ ] Dev ohne Entra möglich: lokale Konten + Seeder unverändert nutzbar.

## Entscheidungen (Freigabe 2026-09-22)

- `entra_object_id`: als **eindeutige Spalte** auf `users` (stabile Zuordnung über Email-Wechsel hinweg). Neue Migration nötig.
- Entra im Template **simuliert** (Socialite-Fake), kein echter Test-Tenant im CI; echte Stringency-Abnahme passiert pro Kundenprojekt.
- Rollen-Default-Set: die vier bestehenden Rollen (`UserRole`: Web-Admin, GF, Bereichsleiter, Mitarbeiter) wandern als **Default-Config in das identity-Modul** (pro Kunde anpassbar).
