<?php

namespace Tests\Feature;

use App\Enums\AuditEventType;
use App\Enums\ShiftStatus;
use App\Livewire\MyShiftsBoard;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftAuditEvent;
use App\Models\User;
use App\Services\EmployeeAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EmployeeAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private function employeeWithUser(): Employee
    {
        return Employee::factory()->create([
            'user_id' => User::factory()->nutzer()->create()->id,
        ]);
    }

    public function test_sick_report_releases_future_shifts_and_logs_ledger_event(): void
    {
        $employee = $this->employeeWithUser();
        $future = Shift::factory()->assigned()->create(['assigned_employee_id' => $employee->id]);
        $past = Shift::factory()->assigned()->create([
            'assigned_employee_id' => $employee->id,
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDays(2)->addHours(8),
        ]);

        $released = app(EmployeeAvailabilityService::class)->reportSick($employee, 'Grippe');

        $this->assertSame(1, $released);
        $this->assertFalse($employee->fresh()->is_active);

        // Künftige Schicht ist wieder offen, die Vergangenheit bleibt unberührt.
        $this->assertSame(ShiftStatus::Open, $future->fresh()->status);
        $this->assertNull($future->fresh()->assigned_employee_id);
        $this->assertSame(ShiftStatus::Assigned, $past->fresh()->status);

        $event = ShiftAuditEvent::latest('version')->first();
        $this->assertSame(AuditEventType::AvailabilityReported, $event->event_type);
        $this->assertSame('Grippe', $event->new_state['availability_reason']);
        $this->assertSame($employee->id, $event->new_state['released_employee_id']);
    }

    public function test_available_report_returns_employee_to_pool(): void
    {
        $employee = Employee::factory()->inactive()->create();

        app(EmployeeAvailabilityService::class)->reportAvailable($employee);

        $this->assertTrue($employee->fresh()->is_active);
    }

    public function test_my_shifts_board_reports_sick_via_self_service(): void
    {
        $employee = $this->employeeWithUser();
        $shift = Shift::factory()->assigned()->create(['assigned_employee_id' => $employee->id]);

        $this->actingAs($employee->user);

        Livewire::test(MyShiftsBoard::class)
            ->set('sickReason', 'Fieber')
            ->call('reportSick')
            ->assertSee('Krankmeldung erfasst')
            ->assertSee('1 Schicht(en)');

        $this->assertFalse($employee->fresh()->is_active);
        // Freigegebene Schicht ist wieder offen (Zuweisung wurde aufgehoben).
        $this->assertSame(ShiftStatus::Open, $shift->fresh()->status);
    }

    public function test_my_shifts_board_shows_only_own_shifts(): void
    {
        $employee = $this->employeeWithUser();
        Shift::factory()->assigned()->create(['assigned_employee_id' => $employee->id, 'title' => 'Meine Schicht']);
        Shift::factory()->assigned()->create(['title' => 'Fremde Schicht']);

        $this->actingAs($employee->user);

        Livewire::test(MyShiftsBoard::class)
            ->assertSee('Meine Schicht')
            ->assertDontSee('Fremde Schicht');
    }
}
