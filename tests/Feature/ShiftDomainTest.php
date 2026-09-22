<?php

namespace Tests\Feature;

use App\Audit\AuditLedger;
use App\Audit\Enums\AuditEventType;
use App\Enums\PipelineDriver;
use App\Enums\ShiftStatus;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftOptimization;
use App\Models\ShiftProposal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_available_scope_excludes_inactive_employees(): void
    {
        Employee::factory()->create(['name' => 'Aktiv']);
        Employee::factory()->inactive()->create(['name' => 'Krank']);

        $this->assertSame(['Aktiv'], Employee::available()->pluck('name')->all());
    }

    public function test_employee_qualification_helpers(): void
    {
        $employee = Employee::factory()->create(['qualifications' => ['Staplerschein']]);

        $this->assertTrue($employee->hasQualification('Staplerschein'));
        $this->assertFalse($employee->hasQualification('Ersthelfer'));
        $this->assertSame(['Ersthelfer'], $employee->missingQualifications(['Staplerschein', 'Ersthelfer']));
    }

    public function test_employee_rest_hours(): void
    {
        $this->assertNull(Employee::factory()->create(['last_shift_ended_at' => null])->restHours());

        $employee = Employee::factory()->create(['last_shift_ended_at' => now()->subHours(12)]);
        $this->assertEqualsWithDelta(12.0, $employee->restHours(), 0.05);
    }

    public function test_open_scope_returns_only_open_shifts(): void
    {
        Shift::factory()->create(['title' => 'Offen']);
        Shift::factory()->assigned()->create(['title' => 'Besetzt']);
        Shift::factory()->cancelled()->create(['title' => 'Storniert']);

        $this->assertSame(['Offen'], Shift::open()->pluck('title')->all());
    }

    public function test_shift_status_is_cast_to_enum_and_snapshot_is_structured(): void
    {
        $shift = Shift::factory()->create();

        $this->assertSame(ShiftStatus::Open, $shift->status);
        $this->assertSame($shift->id, $shift->snapshot()['shift_id']);
        $this->assertSame('open', $shift->snapshot()['status']);
    }

    public function test_proposal_confidence_tier_boundaries(): void
    {
        $tier = fn (int $score): string => ShiftProposal::factory()->make(['score' => $score])->confidenceTier();

        $this->assertSame('high', $tier(90));
        $this->assertSame('medium', $tier(89));
        $this->assertSame('medium', $tier(70));
        $this->assertSame('low', $tier(69));
    }

    public function test_optimization_proposals_are_ordered_by_score_descending(): void
    {
        $optimization = ShiftOptimization::factory()->create();
        ShiftProposal::factory()->for($optimization, 'optimization')->create(['score' => 72]);
        ShiftProposal::factory()->for($optimization, 'optimization')->create(['score' => 95]);

        $this->assertSame([95, 72], $optimization->proposals()->pluck('score')->all());
    }

    public function test_audit_ledger_increments_version_per_shift(): void
    {
        $shift = Shift::factory()->create();
        $ledger = app(AuditLedger::class);

        $first = $ledger->record(AuditEventType::InitialAssignment, [], ['status' => 'assigned'], auditable: $shift);
        $second = $ledger->record(AuditEventType::ManualOverride, [], ['status' => 'assigned'], auditable: $shift);

        $this->assertSame(1, $first->version);
        $this->assertSame(2, $second->version);
        $this->assertSame($first->hash, $second->prev_hash);
        $this->assertTrue($second->isChainValid());
    }

    public function test_audit_event_has_no_updated_at_column(): void
    {
        $event = app(AuditLedger::class)->record(
            AuditEventType::Rollback,
            [],
            ['status' => 'open'],
            auditable: Shift::factory()->create(),
        );

        $this->assertNotNull($event->created_at);
        $this->assertNull($event->getUpdatedAtColumn());
        $this->assertFalse($event->isDirty());
        $this->assertSame(PipelineDriver::Mock->value, ShiftOptimization::factory()->create()->driver_used->value);
    }
}
