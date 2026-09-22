<?php

namespace App\Ai\Pipelines;

use App\Ai\Contracts\AiAgent;
use App\Ai\Data\AiResult;
use App\Ai\Enums\AiDriver;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Responses\StructuredAgentResponse;

/**
 * Agent, der über das Laravel-AI-SDK einen echten Provider anspricht.
 *
 * Der SDK-Agent (z. B. AnonymousAgent oder ein kundenspezifischer Agent aus
 * app/Ai/Agents) erhält den Prompt und liefert eine Text- beziehungsweise
 * strukturierte Antwort. In Tests wird der Provider über den Fake-Gateway
 * ersetzt (Agent::fake([...])), sodass nie echte API-Keys benötigt werden.
 */
final class LaravelAiSdkPipeline implements AiAgent
{
    public function __construct(
        public Agent $agent,
        public ?string $provider = null,
        public ?string $model = null,
    ) {}

    /**
     * Führt den SDK-Agenten aus.
     *
     * @param  array<string, mixed>  $context
     */
    public function run(string $prompt, array $context = []): AiResult
    {
        $start = hrtime(true);

        $response = $this->agent->prompt($prompt, [], $this->provider, $this->model);

        $executionTimeMs = (int) ((hrtime(true) - $start) / 1_000_000);

        // Strukturierte Antworten liefert nur die StructuredAgentResponse.
        $structured = $response instanceof StructuredAgentResponse ? $response->structured : null;

        // Token-Verbrauch im Usage-Format der Laravel-AI-SDK.
        /** @var array{prompt_tokens: int, completion_tokens: int, cache_write_input_tokens: int, cache_read_input_tokens: int, reasoning_tokens: int} $usage */
        $usage = $response->usage->toArray();

        return new AiResult(
            agent: 'laravel-ai',
            driver: AiDriver::Live,
            text: $response->text,
            structured: $structured,
            usage: $usage,
            executionTimeMs: $executionTimeMs,
            conversationId: $response->conversationId,
        );
    }
}
