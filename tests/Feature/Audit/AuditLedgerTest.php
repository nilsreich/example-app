<?php

namespace Tests\Feature\Audit;

use App\Audit\AuditLedger;
use App\Audit\Enums\AuditEventType;
use App\Audit\Enums\AuditSource;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class AuditLedgerTest extends TestCase
{
    use RefreshDatabase;

    private function record(AuditLedger $ledger, Model $auditable, int $version = 0): mixed
    {
        $version = $version > 0 ? $version : 1;

        return $ledger->record(
            eventType: AuditEventType::Updated,
            previousState: ['name' => "Vor-$version"],
            newState: ['name' => "Nach-$version"],
            auditable: $auditable,
        );
    }

    public function test_records_first_event_with_version_one_and_null_prev_hash(): void
    {
        $user = User::factory()->create();

        $event = (new AuditLedger)->record(
            eventType: AuditEventType::Created,
            previousState: [],
            newState: ['name' => 'Alice'],
            auditable: $user,
        );

        $this->assertSame(1, $event->version);
        $this->assertNull($event->prev_hash);
        $this->assertSame(64, strlen((string) $event->hash));
        $this->assertTrue($event->isChainValid());
        $this->assertSame($user->id, $event->auditable_id);
        $this->assertSame($user->getMorphClass(), $event->auditable_type);
    }

    public function test_chains_second_event_to_the_previous_one(): void
    {
        $ledger = new AuditLedger;
        $user = User::factory()->create();

        $first = $this->record($ledger, $user);
        $second = $this->record($ledger, $user, 2);

        $this->assertSame(2, $second->version);
        $this->assertSame($first->hash, $second->prev_hash);
        $this->assertTrue($second->isChainValid());
    }

    public function test_versions_are_per_entity_but_chain_is_global(): void
    {
        $ledger = new AuditLedger;
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $a1 = $this->record($ledger, $userA);
        $b1 = $this->record($ledger, $userB);
        $a2 = $this->record($ledger, $userA, 2);

        $this->assertSame(1, $a1->version);
        $this->assertSame(1, $b1->version);
        $this->assertSame(2, $a2->version);
        $this->assertSame($a1->hash, $b1->prev_hash);
        $this->assertSame($b1->hash, $a2->prev_hash);
        $this->assertTrue($a2->isChainValid());
    }

    public function test_records_actor_and_source(): void
    {
        $ledger = new AuditLedger;
        $actor = User::factory()->create();
        $auditable = User::factory()->create();

        $event = $ledger->record(
            eventType: AuditEventType::StatusChanged,
            previousState: ['status' => 'open'],
            newState: ['status' => 'assigned'],
            auditable: $auditable,
            actor: $actor,
            source: AuditSource::Ai,
            ip: '127.0.0.1',
            userAgent: 'Test-Agent',
        );

        $this->assertSame($actor->id, $event->actor_user_id);
        $this->assertSame($actor->is($event->actor), true);
        $this->assertSame(AuditSource::Ai, $event->source);
        $this->assertSame('ai', $event->source->value);
        $this->assertSame('127.0.0.1', $event->ip);
        $this->assertSame('Test-Agent', $event->user_agent);
        $this->assertTrue($event->isChainValid());
    }

    public function test_records_system_events_without_auditable(): void
    {
        $ledger = new AuditLedger;

        $first = $ledger->record(
            eventType: AuditEventType::Login,
            previousState: [],
            newState: [],
        );
        $second = $ledger->record(
            eventType: AuditEventType::Logout,
            previousState: [],
            newState: [],
        );

        $this->assertNull($first->auditable_type);
        $this->assertNull($first->auditable_id);
        $this->assertSame(1, $first->version);
        $this->assertSame(2, $second->version);
        $this->assertSame($first->hash, $second->prev_hash);
        $this->assertTrue($second->isChainValid());
    }

    public function test_detects_tampered_event_as_invalid(): void
    {
        $ledger = new AuditLedger;
        $user = User::factory()->create();

        $first = $this->record($ledger, $user);
        $second = $this->record($ledger, $user, 2);

        DB::table('audit_events')
            ->where('id', $first->id)
            ->update(['new_state' => json_encode(['name' => 'Eve'], JSON_THROW_ON_ERROR)]);

        $first->refresh();

        $this->assertFalse($first->isChainValid());
        $this->assertTrue($second->isChainValid());
    }
}
