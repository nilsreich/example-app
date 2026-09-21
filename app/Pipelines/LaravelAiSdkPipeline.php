<?php

namespace App\Pipelines;

use App\Ai\Agents\ShiftOptimizerAgent;
use App\Contracts\ShiftOptimizerPipelineInterface;
use App\Data\OptimizationResult;
use App\Data\ProposedMatch;
use App\Enums\PipelineDriver;
use App\Models\Shift;
use App\Services\ShiftCandidateContextBuilder;
use Illuminate\Support\Collection;

/**
 * Live-Treiber auf Basis des Laravel AI SDK (Structured Output):
 * Übergibt denselben Kontext wie der Mock-Treiber und normalisiert
 * die JSON-Antwort auf dasselbe Ergebnisformat (Driver-Switch ohne
 * UI-/Service-Änderungen möglich).
 */
class LaravelAiSdkPipeline implements ShiftOptimizerPipelineInterface
{
    public function __construct(
        private readonly ShiftCandidateContextBuilder $context,
    ) {}

    public function optimize(Shift $shift): OptimizationResult
    {
        $startedAt = hrtime(true);
        $payload = $this->context->for($shift->fresh() ?? $shift);

        // Bekannte IDs vorab einsammeln: Das Modell darf keine Phantom-Kandidaten erfinden.
        $knownIds = Collection::make($payload['candidates'])->map(fn (array $candidate): int => $candidate['employee_id'])->all();

        $response = (new ShiftOptimizerAgent(shift: $payload['shift'], candidates: $payload['candidates']))
            ->prompt($this->promptText($payload));

        $matches = Collection::make($response['matches'] ?? [])
            ->map(fn (array $match): ProposedMatch => ProposedMatch::fromArray($match))
            ->filter(fn (ProposedMatch $match): bool => in_array($match->employeeId, $knownIds, true))
            ->sortByDesc(fn (ProposedMatch $match): int => $match->score)
            ->values()
            ->all();

        $usage = $response->usage;
        $tokensUsed = $usage->promptTokens + $usage->completionTokens + $usage->reasoningTokens;

        return new OptimizationResult(
            driver: PipelineDriver::Live,
            matches: $matches,
            executionTimeMs: (int) ((hrtime(true) - $startedAt) / 1_000_000),
            tokensUsed: $tokensUsed,
            // Keine statische Preisliste im Code: Provider-Tarife ändern sich;
            // Kostenkennzahl folgt in der ROI-Ebene, sobald Tarife konfiguriert sind.
            costEstimateEur: null,
            promptPayload: $payload,
            rawResponse: $response->toArray(),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function promptText(array $payload): string
    {
        return 'Krankmeldung: Die Schicht '.json_encode($payload['shift'], JSON_UNESCAPED_UNICODE)
            .' ist unbesetzt. Verfügbare Mitarbeiter (mit Qualifikationen, Ruhezeiten in Stunden und Überstunden in Minuten): '
            .json_encode($payload['candidates'], JSON_UNESCAPED_UNICODE)
            .' Bewerte alle Kandidaten und liefere die Top-Matches mit Score (0-100), Begründung, Trade-offs und Nachrichtentext auf Deutsch.';
    }
}
