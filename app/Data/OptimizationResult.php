<?php

namespace App\Data;

use App\Enums\PipelineDriver;

/**
 * Normalisiertes Ergebnis eines Pipeline-Laufs – identische Form für
 * Mock- und Live-Treiber (Grundlage für Audit-Trail + Widgets).
 */
final readonly class OptimizationResult
{
    /**
     * @param  list<ProposedMatch>  $matches  Bestes Match zuerst.
     * @param  array<string, mixed>  $promptPayload  Exakter KI-Kontext (revisionssicher).
     * @param  array<string, mixed>  $rawResponse  Treiber-Rohantwort (revisionssicher).
     */
    public function __construct(
        public PipelineDriver $driver,
        public array $matches,
        public int $executionTimeMs,
        public ?int $tokensUsed = null,
        public ?string $costEstimateEur = null,
        public array $promptPayload = [],
        public array $rawResponse = [],
    ) {}

    /**
     * Top-N-Matches (Default: Top 3 für das Dispatching-Slide-Over).
     *
     * @return list<ProposedMatch>
     */
    public function top(int $limit = 3): array
    {
        return array_slice($this->matches, 0, max(1, $limit));
    }
}
