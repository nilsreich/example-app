<?php

namespace Tests\Feature\Ai;

use App\Ai\Data\AiResult;
use App\Ai\Enums\AiDecision;
use App\Ai\Enums\AiDriver;
use App\Ai\Services\AiDecisionAuditor;
use App\Audit\Models\AuditEvent;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiDecisionAuditorTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepted_decision_is_audited_with_ai_source(): void
    {
        $employee = Employee::factory()->create();
        $actor = User::factory()->create();
        $result = new AiResult(
            agent: 'disposition-agent',
            driver: AiDriver::Mock,
            text: 'Empfehlung: Frühschicht an Anna vergeben.',
            conversationId: '01JZ-DEMO-KONVERSATION',
        );

        app(AiDecisionAuditor::class)->record(
            decision: AiDecision::Accepted,
            result: $result,
            auditable: $employee,
            actor: $actor,
        );

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'ai_decision',
            'source' => 'ai',
            'auditable_type' => $employee->getMorphClass(),
            'auditable_id' => $employee->getKey(),
            'actor_user_id' => $actor->getKey(),
            // Der Auditable-Hook des Modells schreibt beim Anlegen "Created" (Version 1).
            'version' => 2,
        ]);

        $event = AuditEvent::query()->where('event_type', 'ai_decision')->firstOrFail();

        $this->assertSame('accepted', $event->new_state['decision']);
        $this->assertSame('disposition-agent', $event->new_state['agent']);
        $this->assertSame('mock', $event->new_state['driver']);
        $this->assertSame('01JZ-DEMO-KONVERSATION', $event->new_state['conversation_id']);
        $this->assertStringContainsString('Frühschicht', $event->new_state['text']);
        $this->assertTrue($event->isChainValid());
    }

    public function test_rejected_decision_is_audited_and_versions_increment(): void
    {
        $employee = Employee::factory()->create();
        $auditor = app(AiDecisionAuditor::class);
        $baseResult = fn (): AiResult => new AiResult(agent: 'disposition-agent', driver: AiDriver::Mock, text: 'Vorschlag');

        $first = $auditor->record(AiDecision::Rejected, $baseResult(), $employee);
        $second = $auditor->record(AiDecision::Accepted, $baseResult(), $employee);

        // Version 2/3: "Created" aus dem Auditable-Hook ist Version 1.
        $this->assertSame(2, (int) $first->version);
        $this->assertSame(3, (int) $second->version);
        $this->assertSame((int) $first->version + 1, (int) $second->version);
        $this->assertSame('rejected', $first->new_state['decision']);
        $this->assertSame('accepted', $second->new_state['decision']);
        $this->assertTrue($second->isChainValid());
        $this->assertSame($first->hash, $second->prev_hash);
    }

    public function test_decision_without_auditable_is_audited_as_system_event(): void
    {
        $result = new AiResult(agent: 'example', driver: AiDriver::Live, text: 'Antwort');

        $event = app(AiDecisionAuditor::class)->record(AiDecision::Accepted, $result);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'ai_decision',
            'source' => 'ai',
            'auditable_type' => null,
            'auditable_id' => null,
            'actor_user_id' => null,
            'version' => 1,
        ]);

        $this->assertSame('example', $event->new_state['agent']);
        $this->assertSame('laravel-ai', $event->new_state['driver']);
        $this->assertTrue($event->isChainValid());
    }
}
