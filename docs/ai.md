# AI-Erweiterungspunkt (ai-Modul)

> Modul-Id: `ai` · Spec: `SPEC-ai.md` · Status: Phase 4 umgesetzt (T9–T11), alle Gates grün.

Das Template liefert die **Infrastruktur** für eigene AI-Agenten — keine fixen
Use-Cases. Kundenprojekte schreiben ihre Agenten in `app/Ai/Agents/` und legen
sie per Konfiguration bei, **ohne Template-Klassen anzufassen**.

## Architektur (Überblick)

```
app/Ai/
  Contracts/AiAgent.php                    # Vertrag: run(string $prompt, array $context): AiResult
  Data/AiResult.php                        # Immutables Ergebnis (agent, driver, text, structured, usage, …)
  Enums/AiDriver.php                       # mock | laravel-ai
  Enums/AiDecision.php                     # accepted | rejected (Human-Entscheidung)
  Pipelines/MockDeterministicPipeline.php  # Fake: deterministisch, ohne Netz/Keys
  Pipelines/LaravelAiSdkPipeline.php       # Default: Prompt über laravel/ai
  Models/AgentConversation.php             # Wrapper auf agent_conversations (Laravel-AI-Tabelle)
  Models/AgentMessage.php                  # Wrapper auf agent_conversation_messages
  Services/AgentRegistry.php               # Agent-Factory über config/ai.php -> agents
  Services/ConversationRecorder.php        # Einzige Schreibstelle für Konversationen/Nachrichten
  Services/AiDecisionAuditor.php           # Schreibt Human-Entscheidungen ins Audit-Ledger (source=ai)
app/Providers/AiServiceProvider.php
```

### Datenfluss

1. Rufer (`AgentRegistry::run(name, prompt, context, participant)`) startet eine
   Konversation über den `ConversationRecorder` — **immer**, auch bei der Mock-Pipeline.
2. Der konfigurierte Agent wird ausgeführt (Mock oder Laravel-AI-SDK).
3. Prompt (user) und Antwort (assistant, mit `usage`) werden persistiert;
   die Konversations-ID landet im `AiResult`.
4. Akzeptiert/lehnt ab der Mensch eine Empfehlung, schreibt
   `AiDecisionAuditor::record(…)` ein `ai_decision`-Event ins Audit-Ledger
   (`source=ai`, inkl. Agent, Treiber, Konversations-ID und Antworttext).

## Konfiguration (`config/ai.php`)

Der Provider-Block bleibt unangetastet (Laravel-AI-Standard). Ergänzt wurden:

```php
'agents' => [
    'example' => [
        'driver'       => env('AI_AGENT_DRIVER', 'mock'), // mock | laravel-ai
        'instructions' => 'Du bist ein hilfreicher Assistent. Antworte kurz und präzise.',
        'provider'     => null, // z. B. 'openai', 'ollama', 'azure' (enum/string des SDK)
        'model'        => null, // z. B. 'gpt-4o-mini', Vorgabe aus Provider
    ],
],
'agent_driver'   => env('AI_AGENT_DRIVER', 'mock'),
'conversations'  => [
    'connection' => null,
    'tables' => [
        'conversations' => 'agent_conversations',
        'messages'      => 'agent_conversation_messages',
    ],
],
```

- **Ohne jeden Konfigurationsaufwand** läuft alles auf der Mock-Pipeline:
  `AI_AGENT_DRIVER` ist nicht gesetzt → `mock`. Kein Test, keine CI benötigt Keys.
- Umstellung auf einen echten Provider pro Deployment:
  `AI_AGENT_DRIVER=laravel-ai` + Key/Endpoint im Provider-Block setzen.

## Neuen Agenten beilegen (Doku-Beispiel)

### Variante A: Nur Konfiguration (SDK-Agent `laravel-ai`)

Anonymer SDK-Agent mit zentralen Instructions — ausreichend für einfache
Prompt-Antworten:

```php
// config/ai.php -> agents
'mein-agent' => [
    'driver'       => env('AI_AGENT_DRIVER', 'mock'),
    'instructions' => 'Du bist ein Terminassistent der Praxis. Antworte auf Deutsch.',
    'provider'     => 'openai',
    'model'        => 'gpt-4o-mini',
],
```

```php
$result = app(AgentRegistry::class)->run(
    name: 'mein-agent',
    prompt: 'Fasse den heutigen Terminplan zusammen.',
    context: ['calendar' => $events],
    participant: $employee,   // optional: Fachbezug für die Konversation
);
```

### Variante B: Eigene Agent-Klasse (strukturierte Antworten)

Für strukturierte Ausgaben wie im Demo-`ShiftOptimizerAgent`
(`app/Ai/Agents/ShiftOptimizerAgent.php` als Referenz!):

```php
namespace App\Ai\Agents;

use Laravel\Ai\Concerns\Promptable;
use Laravel\Ai\Contracts\Agent;

class MeinAssistent implements Agent
{
    use Promptable;

    public function instructions(): string
    {
        return 'Du bist ein Terminassistent. ...';
    }
}
```

Die Pipeline (`LaravelAiSdkPipeline`) nimmt jede `Laravel\Ai\Contracts\Agent`-Instanz;
strukturierte Antworten (`StructuredAgentResponse`) fließen in `AiResult->structured`,
Token-Verbrauch in `AiResult->usage`. Die eigene Klasse wird dann z. B. im
`AgentRegistry::resolveSdkPipeline()` als Factory nachgeschärft oder direkt injiziert.

## Audit-Anbindung

```php
app(AiDecisionAuditor::class)->record(
    decision: AiDecision::Accepted,   // oder Rejected
    result: $result,                  // AiResult aus dem Agent-Lauf
    auditable: $shift,                // Fachbezug (z. B. Shift, Employee)
    actor: auth()->user(),
);
```

Erzeugt ein `audit_events`-Event: `event_type=ai_decision`, `source=ai`,
`new_state` enthält `decision`, `agent`, `driver`, `conversation_id`, `text`.
Damit bleibt „welche AI-Entscheidung hat diese Änderung verursacht?" über die
globale Hash-Kette nachvollziehbar; der Demo-Runner
(`ShiftOptimizationRunner`, Phase 5/T14) wird darauf umgestellt.

## Persistenz & DSGVO-Hinweise

- Konversationen werden in den **bestehenden Laravel-AI-Tabellen** abgelegt
  (`agent_conversations`/`agent_conversation_messages`); Schema bleibt unverändert
  (keine Modul-Migration). Agent-Name, Provider, Request/Response und
  Token-Verbrauch passen in die vorhandenen Spalten (`agent`, `role`, `content`,
  `usage`, `meta`).
- `ConversationRecorder` ist die einzige Schreibstelle — Auditor und Recorder
  kapseln alle Fachlogik, Modelle sind schlanke Wrapper.
- **Kundendaten ohne Prüfung an externe Provider senden ist untersagt** (Boundary).
  Für DSGVO-konforme Deployments: Ollama on-prem oder EU-Endpunkte
  (z. B. Azure OpenAI in der EU) konfigurieren, Opt-in für externe APIs vorsehen.

## Tests & Gates

```bash
php artisan test --filter=Ai   # Modul-Suite (ohne Keys)
composer test                  # volle Suite
~/.config/herd-lite/bin/composer exec pint -- --test
vendor/bin/phpstan analyse --no-progress   # aktuelle Baseline beachten
```

Stand Zuschnitt Phase 4: 203 Tests / 665 Assertions grün, Pint PASS, PHPStan
Baseline 54 (0 Fehler in `app/Ai|Audit|Identity|Feedback`). Kein Test ruft einen
echten Provider auf (Secret-frei über `Promptable::fake()` bzw. Mock-Pipeline).
