<?php

namespace App\Ai\Services;

use App\Ai\Data\AiResult;
use App\Ai\Enums\AiDecision;
use App\Audit\AuditLedger;
use App\Audit\Enums\AuditEventType;
use App\Audit\Enums\AuditSource;
use App\Audit\Models\AuditEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Schreibt AI-Entscheidungen (akzeptiert/abgelehnt) in das Audit-Ledger.
 *
 * Der Eintrag referenziert das AiResult (Agent, Treiber, Antworttext) sowie
 * die persistierte Konversation (conversation_id) und den auditierbaren
 * Fachbezug (auditable). Demodaten wie ShiftOptimization bleiben Domänendaten
 * und werden hier nicht kopiert — verwiesen wird über auditable/Conversation.
 */
final class AiDecisionAuditor
{
    public function __construct(private readonly AuditLedger $ledger) {}

    public function record(
        AiDecision $decision,
        AiResult $result,
        ?Model $auditable = null,
        ?User $actor = null,
        ?string $ip = null,
        ?string $userAgent = null,
    ): AuditEvent {
        return $this->ledger->record(
            eventType: AuditEventType::AiDecision,
            previousState: [],
            newState: [
                'decision' => $decision->value,
                'agent' => $result->agent,
                'driver' => $result->driver->value,
                'conversation_id' => $result->conversationId,
                'text' => $result->text,
            ],
            auditable: $auditable,
            actor: $actor,
            source: AuditSource::Ai,
            ip: $ip,
            userAgent: $userAgent,
        );
    }
}
