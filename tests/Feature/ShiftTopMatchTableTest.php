<?php

namespace Tests\Feature;

use App\Audit\Enums\AuditEventType;
use App\Audit\Models\AuditEvent;
use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Shifts\Pages\ListShifts;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftOptimization;
use App\Models\User;
use App\Pipelines\MockDeterministicPipeline;
use App\Services\ShiftCandidateContextBuilder;
use App\Services\ShiftOptimizationRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Top-Match direkt in der Schichtenliste: Vorschlag anzeigen, akzeptieren
 * (mit Bestätigung) oder bei Bedarf erst berechnen.
 */
class ShiftTopMatchTableTest extends TestCase
{
    use RefreshDatabase;

    private function runPipeline(Shift $shift): ShiftOptimization
    {
        return (new ShiftOptimizationRunner(new MockDeterministicPipeline(new ShiftCandidateContextBuilder, 0)))
            ->run($shift);
    }

    public function test_open_shift_without_run_shows_compute_button(): void
    {
        $this->actingAs(User::factory()->create());
        Shift::factory()->create(['title' => 'Frühschicht Logistik']);

        Livewire::test(ListShifts::class)
            ->assertSee('Noch nicht berechnet')
            ->assertSee('Berechnen')
            ->assertSee('Mehr');
    }

    public function test_top_match_is_shown_in_the_row_after_a_run(): void
    {
        $this->actingAs(User::factory()->create());

        $shift = Shift::factory()->create(['required_qualifications' => ['Staplerschein']]);
        $employee = Employee::factory()->create(['name' => 'Dora Lehmann', 'qualifications' => ['Staplerschein']]);
        $optimization = $this->runPipeline($shift);
        $score = $optimization->proposals->first()->score;

        Livewire::test(ListShifts::class)
            ->assertSee('Dora Lehmann')
            ->assertSee($score.' %')
            ->assertSee('Akzeptieren')
            ->assertSee('Mehr');
    }

    public function test_compute_action_creates_an_optimization_run(): void
    {
        $this->actingAs(User::factory()->create());

        $shift = Shift::factory()->create();
        Employee::factory()->create();

        Livewire::test(ListShifts::class)
            ->callTableAction('compute', $shift)
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('shift_optimizations', ['shift_id' => $shift->id]);
        $this->assertNotNull($shift->fresh()->latestOptimization);
    }

    public function test_accept_action_assigns_employee_and_writes_ledger(): void
    {
        $this->actingAs(User::factory()->create());

        $shift = Shift::factory()->create(['required_qualifications' => []]);
        $employee = Employee::factory()->create(['name' => 'Dora Lehmann']);
        $this->runPipeline($shift);

        Livewire::test(ListShifts::class)
            ->callTableAction('accept', $shift)
            ->assertHasNoTableActionErrors();

        $this->assertSame(ShiftStatus::Assigned, $shift->fresh()->status);
        $this->assertSame($employee->id, $shift->fresh()->assigned_employee_id);
        $this->assertSame(AuditEventType::InitialAssignment, AuditEvent::where('auditable_type', Shift::class)->where('auditable_id', $shift->id)->latest('version')->first()->event_type);
    }

    public function test_accept_action_is_hidden_for_geschaeftsfuehrung(): void
    {
        $shift = Shift::factory()->create(['required_qualifications' => []]);
        Employee::factory()->create(['name' => 'Dora Lehmann']);
        $this->runPipeline($shift);

        // Geschäftsführung: read-only – Vorschlag sichtbar, Übernahme nicht.
        $this->actingAs(User::factory()->create(['role' => UserRole::Geschaeftsfuehrer]));

        Livewire::test(ListShifts::class)
            ->assertSee('Dora Lehmann')
            ->assertTableActionHidden('accept', $shift);
    }

    public function test_nutzer_cannot_open_the_shift_list_at_all(): void
    {
        $this->actingAs(User::factory()->nutzer()->create());

        $this->get('/admin/shifts')->assertForbidden();
    }

    public function test_bereichsleiter_can_accept_in_own_department_only(): void
    {
        $logistics = Shift::factory()->create(['title' => 'Logistik-Schicht A', 'department' => 'Logistik', 'required_qualifications' => []]);
        $production = Shift::factory()->create(['title' => 'Produktions-Schicht B', 'department' => 'Produktion', 'required_qualifications' => []]);
        Employee::factory()->create();
        $this->runPipeline($logistics);
        $this->runPipeline($production);

        $this->actingAs(User::factory()->bereichsleiter('Logistik')->create());

        // Eigene Abteilung: Aktion sichtbar. Fremde Abteilung: nicht Teil der Tabelle.
        Livewire::test(ListShifts::class)
            ->assertTableActionVisible('accept', $logistics)
            ->assertDontSee($production->title);
    }
}
