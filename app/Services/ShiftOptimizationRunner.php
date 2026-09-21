<?php

namespace App\Services;

use App\Contracts\ShiftOptimizerPipelineInterface;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftOptimization;
use App\Models\ShiftProposal;
use Illuminate\Support\Facades\DB;

/**
 * Führt die aktive Pipeline aus und persistiert Lauf + Vorschläge
 * revisionssicher (Audit-Trail für Widgets und Dispatching-UI).
 */
class ShiftOptimizationRunner
{
    public function __construct(
        private readonly ShiftOptimizerPipelineInterface $pipeline,
    ) {}

    public function run(Shift $shift): ShiftOptimization
    {
        $result = $this->pipeline->optimize($shift);

        return DB::transaction(function () use ($shift, $result): ShiftOptimization {
            $optimization = ShiftOptimization::create([
                'shift_id' => $shift->id,
                'driver_used' => $result->driver,
                'prompt_payload' => $result->promptPayload,
                'raw_response' => $result->rawResponse,
                'execution_time_ms' => $result->executionTimeMs,
                'tokens_used' => $result->tokensUsed,
                'cost_estimate_eur' => $result->costEstimateEur,
            ]);

            // Phantom-IDs (nur Live-Treiber möglich) nie persistieren.
            $knownIds = Employee::whereIn('id', array_map(fn ($match) => $match->employeeId, $result->matches))
                ->pluck('id')
                ->all();

            foreach ($result->matches as $match) {
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
}
