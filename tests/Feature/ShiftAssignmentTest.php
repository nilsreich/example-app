<?php

namespace Tests\Feature;

use App\Audit\Enums\AuditEventType;
use App\Audit\Models\AuditEvent;
use App\Enums\ShiftStatus;
use App\Models\Employee;
use App\Models\Shift;
use App\Services\ShiftAssignmentService;
use App\Services\ShiftRollbackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ShiftAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigning_open_shift_records_initial_assignment(): void
    {
        $shift = Shift::factory()->create();
        $employee = Employee::factory()->create();

        $event = app(ShiftAssignmentService::class)->assign($shift, $employee);

        $this->assertSame(AuditEventType::InitialAssignment, $event->event_type);
        $this->assertSame(1, $event->version);
        $this->assertSame(ShiftStatus::Assigned, $shift->fresh()->status);
        $this->assertSame($employee->id, $shift->fresh()->assigned_employee_id);
        $this->assertNull($event->previous_state['assigned_employee_id']);
    }

    public function test_reassigning_records_manual_override_with_incremented_version(): void
    {
        $shift = Shift::factory()->create();
        $replacement = Employee::factory()->create();

        app(ShiftAssignmentService::class)->assign($shift, Employee::factory()->create());
        $event = app(ShiftAssignmentService::class)->assign($shift, $replacement, 'Besserer Match (Score 94).');

        $this->assertSame(AuditEventType::ManualOverride, $event->event_type);
        $this->assertSame(2, $event->version);
        $this->assertSame($replacement->id, $shift->fresh()->assigned_employee_id);
        $this->assertSame('Besserer Match (Score 94).', $event->new_state['note']);
    }

    public function test_assigning_inactive_or_cancelled_shift_throws(): void
    {
        $shift = Shift::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        app(ShiftAssignmentService::class)->assign($shift, Employee::factory()->inactive()->create());
    }

    public function test_assigning_cancelled_shift_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(ShiftAssignmentService::class)->assign(Shift::factory()->cancelled()->create(), Employee::factory()->create());
    }

    public function test_rollback_reopens_shift_as_new_forward_event(): void
    {
        $shift = Shift::factory()->create();
        $assignment = app(ShiftAssignmentService::class)->assign($shift, Employee::factory()->create());

        $event = app(ShiftRollbackService::class)->rollback($shift, 'Mitarbeiter erkrankt', 'Einsatz storniert.');

        $this->assertSame(AuditEventType::Rollback, $event->event_type);
        $this->assertSame(2, $event->version);
        // Der Rollback verweist im Zustand auf das zurückgesetzte Assign-Event (Ledger hat keine reverted-Spalte).
        $this->assertSame($assignment->id, $event->new_state['reverted_event_id']);
        $this->assertSame(ShiftStatus::Open, $shift->fresh()->status);
        $this->assertNull($shift->fresh()->assigned_employee_id);
        $this->assertSame('Mitarbeiter erkrankt', $event->new_state['rollback_reason']);
        // Historie bleibt erhalten: kein DELETE, nur Append.
        $this->assertSame(2, AuditEvent::where('auditable_type', Shift::class)->count());
    }

    public function test_rollback_requires_reason_and_assigned_shift(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(ShiftRollbackService::class)->rollback(Shift::factory()->create(), '   ');
    }

    public function test_rollback_of_open_shift_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(ShiftRollbackService::class)->rollback(Shift::factory()->create(), 'Versehentlich');
    }
}
