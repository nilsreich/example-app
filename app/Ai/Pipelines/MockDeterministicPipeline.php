<?php

namespace App\Ai\Pipelines;

use App\Ai\Contracts\AiAgent;
use App\Ai\Data\AiResult;
use App\Ai\Enums\AiDriver;

/**
 * Deterministischer Fake-Agent.
 *
 * Standard-Treiber (config/ai.php -> "agent_driver" = mock): benötigt keine
 * API-Keys und keine Netzwerkverbindung. Die Antwort ist eine deterministische
 * Spiegelung von Prompt und Kontext, damit Tests ohne Provider reproduzierbar
 * sind. Optional simuliert er eine Latenz (nur zu Demo-Zwecken, Tests: 0).
 */
final class MockDeterministicPipeline implements AiAgent
{
    public function __construct(private int $simulatedLatencyMs = 0) {}

    /**
     * Führt die Simulation aus.
     *
     * @param  array<string, mixed>  $context
     */
    public function run(string $prompt, array $context = []): AiResult
    {
        if ($this->simulatedLatencyMs > 0) {
            usleep($this->simulatedLatencyMs * 1000);
        }

        $start = hrtime(true);

        $structured = [
            'prompt' => $prompt,
            'context' => $context,
            'simulated' => true,
        ];

        $executionTimeMs = (int) ((hrtime(true) - $start) / 1_000_000);

        return new AiResult(
            agent: 'mock',
            driver: AiDriver::Mock,
            text: sprintf('Deterministische Mock-Antwort (Simulation) für Prompt: %s', $prompt),
            structured: $structured,
            executionTimeMs: $executionTimeMs,
        );
    }
}
