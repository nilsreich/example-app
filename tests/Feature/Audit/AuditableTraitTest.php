<?php

namespace Tests\Feature\Audit;

use App\Audit\Enums\AuditEventType;
use App\Audit\Models\AuditEvent;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AuditableTraitTest extends TestCase
{
    use RefreshDatabase;

    private function findEvent(Employee $employee, AuditEventType $type): AuditEvent
    {
        return AuditEvent::query()
            ->where('auditable_type', $employee->getMorphClass())
            ->where('auditable_id', $employee->id)
            ->where('event_type', $type)
            ->orderByDesc('id')
            ->firstOrFail();
    }

    public function test_auditable_model_logs_created_event(): void
    {
        $employee = Employee::factory()->create(['name' => 'Mia']);

        $event = $this->findEvent($employee, AuditEventType::Created);

        $this->assertSame('Mia', $event->new_state['name']);
        $this->assertSame([], $event->previous_state);
        $this->assertTrue($event->isChainValid());
    }

    public function test_auditable_model_logs_updated_event_with_previous_and_new_state(): void
    {
        $employee = Employee::factory()->create(['name' => 'Mia']);

        $employee->update(['name' => 'Milo']);

        $event = $this->findEvent($employee, AuditEventType::Updated);

        $this->assertSame('Mia', $event->previous_state['name']);
        $this->assertSame('Milo', $event->new_state['name']);
        $this->assertTrue($event->isChainValid());
    }

    public function test_auditable_model_logs_deleted_event(): void
    {
        $employee = Employee::factory()->create();

        $employee->delete();

        $event = $this->findEvent($employee, AuditEventType::Deleted);

        $this->assertSame($employee->getMorphClass(), $event->auditable_type);
        $this->assertSame($employee->id, $event->auditable_id);
        $this->assertSame([], $event->new_state);
        $this->assertTrue($event->isChainValid());
    }
}
