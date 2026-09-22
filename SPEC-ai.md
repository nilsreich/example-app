# Spec: ai

Modul-Id: `ai` (Capability Map: B2E-App-Template). Freigabestatus: **freigegeben (2026-09-22)**.

## Objective

Laravel-AI-Fähigkeit als **Erweiterungspunkt**, nicht als fixe Use-Cases. Das Template liefert die Infrastruktur, Kundenprojekte schreiben ihre eigenen Agenten.

- `laravel/ai` ist integriert (`config/ai.php`: Provider openai/anthropic/azure/gemini/mistral/ollama/… konfigurierbar; EU-freundliche Optionen: Azure OpenAI in EU, OpenAI-kompatible Endpunkte, Ollama on-prem).
- **Agent-Abstraktion**: Vertrag (`ShiftOptimizerPipelineInterface` wird generalisiert zu `AiAgentContract` o. ä.), Default-Implementierung (`LaravelAiSdkPipeline`) und **Fake** (`MockDeterministicPipeline`) für Tests/CI/Offline-Demos ohne externe Keys.
- Provider-Umschaltung zur Laufzeit über Config/ENV pro Deployment; kein Use-Case hart verdrahtet.
- Konversations-/Agent-Persistenz: `agent_conversations` (bestehende Tabelle) wird generalisiert (Agent-Name, Provider, Request/Response, Token-Verbrauch).
- **Audit-Anbindung**: AI-Events (Auslöser, akzeptiert/abgelehnt, Result) laufen über `audit` — „Welche AI-Entscheidung hat diese Änderung verursacht?" bleibt nachvollziehbar.
- Beispieldomäne: `ShiftOptimizerAgent` (Demo) als Referenz, wie ein Kunden-Agent aussieht. Weitere Use-Cases sind bewusst außerhalb des Templates.

## Tech Stack

- `laravel/ai ^0.11.2` (vorhanden), Laravel 13, PHP 8.3
- Keine neuen AI-Anbieter-Dependencies im Template (nur die SDK-Provider, die `laravel/ai` mitbringt)

## Commands

```
composer test
php artisan test --filter=Ai
php artisan tinker                       # manueller Smoke mit Fake/Provider (Dev)
```

## Project Structure

```
app/Ai/
  Agents/*                              # Kunden-/Demo-Agenten (z. B. ShiftOptimizerAgent)
  Contracts/AiAgent.php                 # generalisierter Vertrag (aus ShiftOptimizerPipelineInterface)
  Pipelines/LaravelAiSdkPipeline.php    # Default
  Pipelines/MockDeterministicPipeline.php # Fake für Tests/Offline-Demos
  Models/AgentConversation.php          # Generalisierung der existierenden Tabelle
  Services/AgentRegistry.php            # Agent-Factory über Config/Registry
config/ai.php                           # unverändert (Provider), ggf. + registry-Mapping
tests/Feature/Ai/...
tests/Unit/Ai/AgentRegistryTest.php
```

## Code Style

Wie Repo (Pint/PHPStan). Prompt-Texte zentral (nicht in Domänencode verstreut), Versionierung von Prompts erwünscht (Config oder eigene Tabelle — offen). UI-Texte Deutsch, Prompts Englisch (gängig für Modelle) — Ausnahme dokumentiert.

## Testing Strategy

- Unit: `AgentRegistry` löst Agenten über Config auf; Contract-Einhaltung der Agenten.
- Feature: Pipeline läuft mit Fake deterministisch (keine Netzwerk-Abhängigkeit); Konversation wird persistiert; AI erzeugtes/akzeptiertes Ergebnis лanded im `audit`-Trail.
- Kein CI-Test ruft echte Provider auf (Secret-frei).

## Boundaries

- **Always:** `composer test`; Agenten nur über Registry/Config; Prompts versioniert/zentral; Ergebnis-/Token-Verbrauch protokollierbar.
- **Ask first:** neue AI-Anbieter-Pakete; Schema-Änderungen an `agent_conversations`; Prompt-/Modell-Releases.
- **Never:** API-Keys im VCS; echte Provider-Aufrufe in Tests; Kundendaten ohne Prüfung an Provider senden (DSGVO: lokal/Ollama/Opt-in für externe APIs).

## Success Criteria

- [ ] `AiAgent`-Contract + Registry + Fake-Pipeline; `ShiftOptimizerAgent` nutzt den neuen Erweiterungspunkt.
- [ ] Ein neuer Kunden-Agent ist als `app/Ai/Agents/*` + Registry-Eintrag ohne Template-Eingriff möglich.
- [ ] `agent_conversations` generalisiert (Agent, Provider, Request/Response, Tokens).
- [ ] AI-Entscheidung → `audit`-Bezug nachvollziehbar (akzeptiert/abgelehnt inkl. Result).
- [ ] `composer test` grün ohne externe Keys (Fake).

## Entscheidungen (Freigabe 2026-09-22)

- **Prompt-Management**: zentrale Config (kein DB-Versioning zu Beginn); Prompts versioniert durch Kommentar/Feld in der Config.
- **Kein generischer Chat-/Assistent** im Template — das bleibt bewusst Kundenprojekt-Arbeit.
