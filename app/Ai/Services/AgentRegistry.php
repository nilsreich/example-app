<?php

namespace App\Ai\Services;

use App\Ai\Contracts\AiAgent;
use App\Ai\Data\AiResult;
use App\Ai\Pipelines\LaravelAiSdkPipeline;
use App\Ai\Pipelines\MockDeterministicPipeline;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Laravel\Ai\AnonymousAgent;
use Throwable;

/**
 * Löst AI-Agenten anhand der Konfiguration (config/ai.php -> "agents") auf.
 *
 * Neue Agenten werden per Konfiguration beigelegt, ohne dass Template-Klassen
 * angefasst werden müssen. Der jeweilige Treiber bestimmt, ob die Mock- oder
 * die SDK-Pipeline verwendet wird.
 */
final class AgentRegistry
{
    /** @var array<string, AiAgent> */
    private array $resolved = [];

    public function __construct(private readonly ConversationRecorder $recorder) {}

    /**
     * Führt einen Agenten aus und zeichnet die Konversation auf.
     *
     * Konversation und Nachrichten (Prompt + Antwort) werden immer
     * persistiert — auch bei der Mock-Pipeline. Die Konversations-ID wird
     * nachträglich in das (immutable) Ergebnis gesetzt.
     *
     * @param  array<string, mixed>  $context
     */
    public function run(
        string $name,
        string $prompt,
        array $context = [],
        ?Model $participant = null,
    ): AiResult {
        $agent = $this->agent($name);
        $conversation = $this->recorder->start($name, $participant);

        $this->recorder->recordMessage(
            conversation: $conversation,
            role: 'user',
            content: $prompt,
            agent: $name,
        );

        try {
            $result = $agent->run($prompt, $context);
        } catch (Throwable $exception) {
            // Keine Waise: auch ein fehlgeschlagener Lauf wird als
            // Assistant-Nachricht festgehalten, dann weitergereicht.
            $this->recorder->recordMessage(
                conversation: $conversation,
                role: 'assistant',
                content: 'Fehler bei der Agentenausführung: '.$exception->getMessage(),
                agent: $name,
                meta: ['error' => true],
            );

            throw $exception;
        }

        $this->recorder->recordMessage(
            conversation: $conversation,
            role: 'assistant',
            content: $result->text,
            agent: $name,
            meta: ['driver' => $result->driver->value],
            usage: $result->usage,
        );

        /** @var string $conversationId */
        $conversationId = $conversation->getKey();

        return $result->withConversationId($conversationId);
    }

    public function agent(string $name): AiAgent
    {
        if (isset($this->resolved[$name])) {
            return $this->resolved[$name];
        }

        $config = config("ai.agents.{$name}");

        if (! is_array($config)) {
            throw new InvalidArgumentException(
                sprintf('Agent [%s] ist nicht in der Konfiguration config/ai.php registriert.', $name)
            );
        }

        // Eigene Agent-Klasse (Docs Variante B): die Registry instanziiert den
        // Agenten über den Container – keine Template-Anpassung nötig.
        if (isset($config['class'])) {
            return $this->resolved[$name] = $this->resolveAgentClass($config);
        }

        $driver = $config['driver'] ?? config('ai.agent_driver', 'mock');

        $agent = match ($driver) {
            'mock' => new MockDeterministicPipeline,
            'laravel-ai' => $this->resolveSdkPipeline($config),
            default => throw new InvalidArgumentException(
                sprintf('Unbekannter AI-Treiber [%s] für Agent [%s].', (string) $driver, $name)
            ),
        };

        return $this->resolved[$name] = $agent;
    }

    /**
     * Instanziiert eine konfigurierte Agent-Klasse über den Container und
     * reicht optionale provider/model-Werte durch (statt toter Config-Keys).
     *
     * @param  array<string, mixed>  $config
     */
    private function resolveAgentClass(array $config): AiAgent
    {
        $class = (string) $config['class'];

        /** @var array<string, mixed> $parameters */
        $parameters = array_filter(
            [
                'provider' => $config['provider'] ?? null,
                'model' => $config['model'] ?? null,
            ],
            fn (mixed $value): bool => $value !== null,
        );

        /** @var AiAgent $agent */
        $agent = app()->makeWith($class, $parameters);

        return $agent;
    }

    /**
     * Baut die SDK-Pipeline aus den Konfigurationswerten.
     *
     * @param  array<string, mixed>  $config
     */
    private function resolveSdkPipeline(array $config): LaravelAiSdkPipeline
    {
        $instructions = $config['instructions'] ?? 'Du bist ein hilfreicher Assistent.';

        return new LaravelAiSdkPipeline(
            agent: new AnonymousAgent($instructions, [], []),
            provider: isset($config['provider']) ? (string) $config['provider'] : null,
            model: isset($config['model']) ? (string) $config['model'] : null,
        );
    }
}
