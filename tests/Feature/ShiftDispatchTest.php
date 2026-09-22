<?php

namespace Tests\Feature;

use App\Audit\Enums\AuditEventType;
use App\Audit\Models\AuditEvent;
use App\Enums\FeedbackRating;
use App\Enums\ShiftStatus;
use App\Livewire\ShiftDispatch;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftFeedback;
use App\Models\ShiftProposal;
use App\Models\User;
use App\Services\ShiftOptimizationRunner;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ShiftDispatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Dispatching erfordert eine Rolle mit Dispositionsrecht (Rechtematrix).
        $this->actingAs(User::factory()->create());
    }

    public function test_run_optimization_persists_matches_for_display(): void
    {
        $shift = Shift::factory()->create(['required_qualifications' => ['Staplerschein']]);
        Employee::factory()->create(['qualifications' => ['Staplerschein']]);

        Livewire::test(ShiftDispatch::class, ['shiftId' => $shift->id])
            ->call('runOptimization')
            ->assertSee('Pipeline-Lauf abgeschlossen')
            ->assertSee('Staplerschein');

        $this->assertDatabaseHas('shift_optimizations', ['shift_id' => $shift->id, 'driver_used' => 'mock']);
        $this->assertNotEmpty(Shift::find($shift->id)->optimizations()->first()->proposals);
    }

    public function test_assign_proposal_assigns_employee_and_writes_ledger(): void
    {
        $shift = Shift::factory()->create(['required_qualifications' => []]);
        Employee::factory()->create();

        $component = Livewire::test(ShiftDispatch::class, ['shiftId' => $shift->id])->call('runOptimization');
        $proposalId = Shift::find($shift->id)->optimizations()->first()->proposals()->first()->id;

        $component
            ->set('drafts.'.$proposalId, 'Hallo, bitte einspringen!')
            ->call('assignProposal', $proposalId)
            ->assertSee('wurde zugewiesen');

        $this->assertSame(ShiftStatus::Assigned, $shift->fresh()->status);
        $this->assertSame('Hallo, bitte einspringen!', $proposalId ? ShiftProposal::find($proposalId)->draft_message : null);
        $this->assertSame(AuditEventType::InitialAssignment, AuditEvent::where('auditable_type', Shift::class)->where('auditable_id', $shift->id)->first()->event_type);
    }

    public function test_positive_feedback_is_recorded_directly(): void
    {
        $shift = Shift::factory()->create(['required_qualifications' => []]);
        Employee::factory()->create();

        $component = Livewire::test(ShiftDispatch::class, ['shiftId' => $shift->id])->call('runOptimization');
        $proposalId = Shift::find($shift->id)->optimizations()->first()->proposals()->first()->id;

        $component->call('openFeedback', $proposalId, FeedbackRating::Positive->value);

        $this->assertDatabaseHas('shift_feedbacks', ['proposal_id' => $proposalId, 'rating' => 'positive']);
    }

    public function test_negative_feedback_requires_category(): void
    {
        $shift = Shift::factory()->create(['required_qualifications' => []]);
        Employee::factory()->create();

        $component = Livewire::test(ShiftDispatch::class, ['shiftId' => $shift->id])->call('runOptimization');
        $proposalId = Shift::find($shift->id)->optimizations()->first()->proposals()->first()->id;

        $component
            ->call('openFeedback', $proposalId, FeedbackRating::Negative->value)
            ->set('feedbackCategory', 'Regelkonflikt')
            ->set('feedbackComment', 'Ruhezeit zu kurz.')
            ->call('submitFeedback')
            ->assertSee('Negatives Feedback gespeichert');

        $this->assertDatabaseHas('shift_feedbacks', [
            'proposal_id' => $proposalId,
            'rating' => 'negative',
            'reason_category' => 'Regelkonflikt',
        ]);
    }

    public function test_negative_feedback_without_category_fails_validation(): void
    {
        $shift = Shift::factory()->create(['required_qualifications' => []]);
        Employee::factory()->create();

        $component = Livewire::test(ShiftDispatch::class, ['shiftId' => $shift->id])->call('runOptimization');
        $proposalId = Shift::find($shift->id)->optimizations()->first()->proposals()->first()->id;

        $component
            ->call('openFeedback', $proposalId, FeedbackRating::Negative->value)
            ->call('submitFeedback')
            ->assertHasErrors(['feedbackCategory']);

        $this->assertSame(0, ShiftFeedback::count());
    }

    public function test_rollback_flow_reopens_shift(): void
    {
        $shift = Shift::factory()->assigned()->create();

        Livewire::test(ShiftDispatch::class, ['shiftId' => $shift->id])
            ->set('rollbackReason', 'Mitarbeiter erneut erkrankt')
            ->call('submitRollback')
            ->assertSee('zurückgerollt');

        $this->assertSame(ShiftStatus::Open, $shift->fresh()->status);

        // Gezielte Suche: das AiDecision-Event wird nach dem Rollback-Ereignis
        // auditiert, `latest('version')` wäre jetzt das ai_decision-Event.
        $rollback = AuditEvent::where('auditable_type', Shift::class)
            ->where('auditable_id', $shift->id)
            ->where('event_type', AuditEventType::Rollback->value)
            ->first();

        $this->assertNotNull($rollback);
    }

    public function test_assign_proposal_records_ai_decision_accepted(): void
    {
        $shift = Shift::factory()->create(['required_qualifications' => []]);
        Employee::factory()->qualified()->create();

        $component = Livewire::test(ShiftDispatch::class, ['shiftId' => $shift->id])->call('runOptimization');
        $proposalId = Shift::find($shift->id)->optimizations()->first()->proposals()->first()->id;

        $component->call('assignProposal', $proposalId);

        $event = AuditEvent::where('auditable_type', Shift::class)
            ->where('auditable_id', $shift->id)
            ->where('event_type', AuditEventType::AiDecision)
            ->first();

        $this->assertNotNull($event);
        $this->assertSame('accepted', $event->new_state['decision']);
        $this->assertNotNull($event->new_state['conversation_id'] ?? null);
        $this->assertSame($shift->id, $event->auditable_id);
    }

    public function test_rollback_records_ai_decision_rejected(): void
    {
        $shift = Shift::factory()->create(['required_qualifications' => []]);
        Employee::factory()->create();

        $component = Livewire::test(ShiftDispatch::class, ['shiftId' => $shift->id])->call('runOptimization');
        $proposalId = Shift::find($shift->id)->optimizations()->first()->proposals()->first()->id;
        $component->call('assignProposal', $proposalId);

        $component
            ->set('rollbackReason', 'Mitarbeiter erneut erkrankt')
            ->call('submitRollback');

        $event = AuditEvent::where('auditable_type', Shift::class)
            ->where('auditable_id', $shift->id)
            ->where('event_type', AuditEventType::AiDecision)
            ->latest('version')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame('rejected', $event->new_state['decision']);
    }

    public function test_feedback_modal_and_emoji_buttons_are_accessible(): void
    {
        $shift = Shift::factory()->create(['required_qualifications' => []]);
        Employee::factory()->create();

        $component = Livewire::test(ShiftDispatch::class, ['shiftId' => $shift->id])->call('runOptimization');
        $proposalId = Shift::find($shift->id)->optimizations()->first()->proposals()->first()->id;

        $component
            ->assertSee('role="status"', escape: false)
            ->assertSee('aria-label="Als hilfreich bewerten"', escape: false)
            ->assertSee('aria-label="Als nicht hilfreich bewerten"', escape: false)
            ->call('openFeedback', $proposalId, FeedbackRating::Negative->value)
            ->assertSee('role="dialog"', escape: false)
            ->assertSee('aria-modal="true"', escape: false)
            ->assertSee('aria-labelledby="feedback-modal-title"', escape: false)
            ->assertSee('id="feedback-modal-title"', escape: false);
    }

    public function test_rollback_modal_is_accessible(): void
    {
        $shift = Shift::factory()->assigned()->create();

        Livewire::test(ShiftDispatch::class, ['shiftId' => $shift->id])
            ->set('showRollbackModal', true)
            ->assertSee('role="dialog"', escape: false)
            ->assertSee('aria-modal="true"', escape: false)
            ->assertSee('aria-labelledby="rollback-modal-title"', escape: false)
            ->assertSee('id="rollback-modal-title"', escape: false);
    }

    public function test_foreign_proposal_id_cannot_receive_feedback(): void
    {
        // Fremder Lauf einer anderen Schicht (Versand liegt außerhalb des Disponent-Scopes).
        $otherShift = Shift::factory()->create(['required_qualifications' => [], 'department' => 'Versand']);
        Employee::factory()->create();
        app(ShiftOptimizationRunner::class)->run($otherShift);
        $foreignProposalId = $otherShift->optimizations()->first()->proposals()->first()->id;

        // Eigene Schicht laden, dann fremde proposal_id einschleusen.
        $shift = Shift::factory()->create(['required_qualifications' => []]);
        Employee::factory()->create();

        try {
            Livewire::test(ShiftDispatch::class, ['shiftId' => $shift->id])
                ->call('runOptimization')
                ->call('openFeedback', $foreignProposalId, FeedbackRating::Positive->value);
            $this->fail('Fremder Vorschlag wurde akzeptiert.');
        } catch (ModelNotFoundException) {
            // Erwartet: der Vorschlag gehört nicht zum geladenen Lauf dieser Schicht.
        }

        $this->assertSame(0, ShiftFeedback::where('proposal_id', $foreignProposalId)->count());
    }

    public function test_tampered_optimization_id_does_not_leak_foreign_runs(): void
    {
        $foreignShift = Shift::factory()->create(['required_qualifications' => [], 'department' => 'Versand']);
        Employee::factory()->create();
        $foreignOptimizationId = app(ShiftOptimizationRunner::class)->run($foreignShift)->id;

        $ownShift = Shift::factory()->create(['required_qualifications' => []]);
        Employee::factory()->create();

        // Ein fremd gesetzter optimizationId darf den fremden Lauf nicht anzeigen:
        // loadOptimization() scoped auf die geladene Schicht (hier ohne Lauf) → null.
        Livewire::test(ShiftDispatch::class, ['shiftId' => $ownShift->id])
            ->call('runOptimization')
            ->set('optimizationId', $foreignOptimizationId)
            ->assertSee('Noch keine Vorschläge berechnet')
            ->assertDontSee('Lauf #'.$foreignOptimizationId);
    }
}
