<?php

namespace Tests\Feature\Audit;

use App\Audit\Enums\AuditEventType;
use App\Audit\Enums\AuditSource;
use App\Audit\Models\AuditEvent;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AuditEventModelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Platzhalter-Hash: Der Ledger (T2) berechnet echte Ketten-Hashes;
     * das Modell selbst ist passiv.
     */
    private const DUMMY_HASH = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function test_creates_audit_event_with_casts(): void
    {
        $event = AuditEvent::query()->create([
            'event_type' => AuditEventType::Login,
            'source' => AuditSource::Web,
            'previous_state' => ['status' => 'open'],
            'new_state' => ['status' => 'assigned'],
            'version' => 1,
            'hash' => self::DUMMY_HASH,
        ]);

        $this->assertInstanceOf(AuditEventType::class, $event->event_type);
        $this->assertSame(AuditEventType::Login, $event->event_type);
        $this->assertSame(AuditSource::Web, $event->source);
        $this->assertSame(['status' => 'open'], $event->previous_state);
        $this->assertSame(['status' => 'assigned'], $event->new_state);
    }

    public function test_audit_event_cannot_be_updated(): void
    {
        $event = AuditEvent::query()->create([
            'event_type' => AuditEventType::Created,
            'source' => AuditSource::Web,
            'hash' => self::DUMMY_HASH,
        ]);

        $this->expectException(\RuntimeException::class);

        $event->update(['new_state' => ['tampered' => true]]);
    }

    public function test_audit_event_cannot_be_deleted(): void
    {
        $event = AuditEvent::query()->create([
            'event_type' => AuditEventType::Created,
            'source' => AuditSource::Web,
            'hash' => self::DUMMY_HASH,
        ]);

        $this->expectException(\RuntimeException::class);

        $event->delete();
    }

    public function test_audit_event_carries_only_created_at(): void
    {
        $event = AuditEvent::query()->create([
            'event_type' => AuditEventType::Created,
            'source' => AuditSource::Cli,
            'hash' => self::DUMMY_HASH,
        ]);

        $fresh = $event->fresh();

        $this->assertNotNull($fresh->created_at);
        $this->assertNull($fresh->updated_at);
    }

    public function test_audit_event_belongs_to_actor(): void
    {
        $actor = User::factory()->create();

        $event = AuditEvent::query()->create([
            'actor_user_id' => $actor->id,
            'event_type' => AuditEventType::Login,
            'source' => AuditSource::Web,
            'hash' => self::DUMMY_HASH,
        ]);

        $this->assertTrue($event->actor->is($actor));
    }

    public function test_audit_event_has_morph_auditable(): void
    {
        $employee = Employee::factory()->create();

        $event = AuditEvent::query()->create([
            'auditable_type' => $employee->getMorphClass(),
            'auditable_id' => $employee->id,
            'event_type' => AuditEventType::Created,
            'source' => AuditSource::Cli,
            'hash' => self::DUMMY_HASH,
        ]);

        $this->assertTrue($event->auditable->is($employee));
    }
}
