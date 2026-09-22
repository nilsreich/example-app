<?php

namespace App\Services;

use App\Ai\Data\AiResult;
use App\Ai\Enums\AiDecision;
use App\Ai\Enums\AiDriver;
use App\Ai\Services\AiDecisionAuditor;
use App\Audit\Models\AuditEvent;
use App\Models\Shift;
use App\Models\ShiftOptimization;
use App\Models\ShiftProposal;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Gemeinsamer Accept-Pfad für KI-Vorschläge: ein Service für alle UI-Pfade
 * (Schichtenliste/Akzeptieren, ShiftDispatch-Slide-Over), damit die
 * „Mensch hat KI-Vorschlag akzeptiert"-Spur immer gleich geschrieben wird.
 *
 * - Autorisierung gegen die Rechtematrix (update-Policy, Abteilungs-Scope).
 * - Zuweisung via ShiftAssignmentService (Ledger: InitialAssignment/ManualOverride).
 * - AI-Entscheidung (accepted) mit conversation_id aus der Optimization.
 */
class ShiftProposalAcceptService
{
    public function __construct(
        private readonly ShiftAssignmentService $assignments,
        private readonly AiDecisionAuditor $aiDecisions,
    ) {}

    public function accept(Shift $shift, ShiftProposal $proposal, ?User $actor = null): AuditEvent
    {
        $actor ??= auth()->user();

        Gate::forUser($actor)->authorize('update', $shift);

        /** @var ShiftOptimization|null $latestOpt */
        $latestOpt = $shift->latestOptimization;

        if ($latestOpt === null || $latestOpt->id !== $proposal->optimization_id) {
            throw new \InvalidArgumentException('Der Vorschlag gehört nicht zum aktuellen Optimierungslauf der Schicht.');
        }

        $assignment = $this->assignments->assign($shift, $proposal->employee);

        $this->recordDecision($shift, $proposal, $actor);

        return $assignment;
    }

    private function recordDecision(Shift $shift, ShiftProposal $proposal, ?User $actor): void
    {
        $raw = $proposal->optimization->raw_response ?? [];
        $conversationId = $raw['conversation_id'] ?? null;

        $this->aiDecisions->record(
            decision: AiDecision::Accepted,
            result: new AiResult(
                agent: (string) ($raw['agent'] ?? 'shift-optimizer'),
                driver: AiDriver::tryFrom((string) ($raw['driver'] ?? 'mock')) ?? AiDriver::Mock,
                text: (string) ($raw['text'] ?? ''),
                conversationId: is_string($conversationId) ? $conversationId : null,
            ),
            auditable: $shift,
            actor: $actor,
        );
    }
}
