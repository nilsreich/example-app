<?php

namespace Tests\Feature;

use App\Enums\AuditEventType;
use App\Enums\FeedbackRating;
use App\Enums\ShiftStatus;
use App\Livewire\ShiftDispatch;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftAuditEvent;
use App\Models\ShiftFeedback;
use App\Models\ShiftProposal;
use App\Models\User;
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
        $shift = Shift::factory()->create();
        $employee = Employee::factory()->create();

        $component = Livewire::test(ShiftDispatch::class, ['shiftId' => $shift->id])->call('runOptimization');
        $proposalId = Shift::find($shift->id)->optimizations()->first()->proposals()->first()->id;

        $component
            ->set('drafts.'.$proposalId, 'Hallo, bitte einspringen!')
            ->call('assignProposal', $proposalId)
            ->assertSee('wurde zugewiesen');

        $this->assertSame(ShiftStatus::Assigned, $shift->fresh()->status);
        $this->assertSame('Hallo, bitte einspringen!', $proposalId ? ShiftProposal::find($proposalId)->draft_message : null);
        $this->assertSame(AuditEventType::InitialAssignment, ShiftAuditEvent::first()->event_type);
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
        $this->assertSame(AuditEventType::Rollback, ShiftAuditEvent::latest('version')->first()->event_type);
    }
}
