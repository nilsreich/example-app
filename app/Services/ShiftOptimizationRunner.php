<?php

namespace App\Services;

use App\Ai\Data\AiResult;
use App\Ai\Enums\AiDriver;
use App\Ai\Services\AgentRegistry;
use App\Data\ProposedMatch;
use App\Enums\PipelineDriver;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftOptimization;
use App\Models\ShiftProposal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Führt einen Schicht-Optimierungs-Lauf über die AgentRegistry aus und
 * persistiert das Ergebnis (Optimization + Proposals) in der Demo-Domäne.
 *
 * Seit Phase 5/T14 läuft die Berechnung über den generischen AiAgent-Vertrag
 * (Agent "shift-optimizer" aus config/ai.php) – die frühere Doppel-Implementierung
 * shift-spezifischer Pipeline-Klassen wurde ersatzlos entfernt.
 */
class ShiftOptimizationRunner
{
    public function __construct(
        private readonly AgentRegistry $registry,
        private readonly ShiftCandidateContextBuilder $context,
    ) {}

    public function run(Shift $shift): ShiftOptimization
    {
        $payload = $this->context->for($shift->fresh() ?? $shift);

        $result = $this->registry->run(
            name: 'shift-optimizer',
            prompt: $this->promptText($payload),
            context: [
                'payload' => $payload,
                'shift' => $shift,
            ],
            participant: $shift,
        );

        return DB::transaction(function () use ($shift, $payload, $result) {
            /** @var list<array<string, mixed>> $rawMatches */
            $rawMatches = is_array($result->structured) ? ($result->structured['matches'] ?? []) : [];

            $matches = Collection::make($rawMatches)
                ->map(fn (array $match) => ProposedMatch::fromArray($match))
                ->all();

            $driver = $result->driver === AiDriver::Live ? PipelineDriver::Live : PipelineDriver::Mock;

            $optimization = ShiftOptimization::create([
                'shift_id' => $shift->id,
                'driver_used' => $driver,
                'prompt_payload' => $payload,
                'raw_response' => array_merge($result->rawResponse ?? [], [
                    'driver' => $driver->value,
                    'matches' => array_map(fn (ProposedMatch $match) => $match->toArray(), $matches),
                    'conversation_id' => $result->conversationId,
                    'agent' => $result->agent,
                    'text' => $result->text,
                ]),
                'execution_time_ms' => $result->executionTimeMs,
                'tokens_used' => $this->tokensUsed($result),
                'cost_estimate_eur' => $driver === PipelineDriver::Mock ? '0.000000' : null,
            ]);

            $knownIds = Employee::query()
                ->whereIn('id', array_map(fn (ProposedMatch $match) => $match->employeeId, $matches))
                ->pluck('id')
                ->all();

            foreach ($matches as $match) {
                if (! in_array($match->employeeId, $knownIds, true)) {
                    continue;
                }

                ShiftProposal::create([
                    'optimization_id' => $optimization->id,
                    'employee_id' => $match->employeeId,
                    'score' => $match->score,
                    'match_reasons' => $match->matchReasons,
                    'risks_or_tradeoffs' => $match->risksOrTradeoffs,
                    'draft_message' => $match->draftMessage,
                ]);
            }

            return $optimization->load(['proposals.employee', 'shift']);
        });
    }

    /**
     * Deutscher Prompt für die Kandidatenbewertung (strukturierte Antwort).
     *
     * @param  array<string, mixed>  $payload
     */
    private function promptText(array $payload): string
    {
        return 'Krankmeldung: Die Schicht '.json_encode($payload['shift'], JSON_UNESCAPED_UNICODE).' ist unbesetzt. '
            .'Verfügbare Mitarbeiter (mit Qualifikationen, Ruhezeiten in Stunden und Überstunden in Minuten): '
            .json_encode($payload['candidates'], JSON_UNESCAPED_UNICODE)
            .' Bewerte alle Kandidaten und liefere die Top-Matches mit Score (0-100), Begründung, Trade-offs und Nachrichtentext auf Deutsch.';
    }

    private function tokensUsed(AiResult $result): ?int
    {
        $usage = $result->usage ?? [];

        $tokens = 0;
        $found = false;

        foreach (['prompt_tokens', 'completion_tokens', 'reasoning_tokens'] as $key) {
            $value = $usage[$key] ?? null;

            if (is_numeric($value)) {
                $tokens += (int) $value;
                $found = true;
            }
        }

        return $found ? $tokens : null;
    }
}
