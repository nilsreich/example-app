<?php

namespace Tests\Feature\Audit;

use App\Audit\AuditLedger;
use App\Audit\Enums\AuditEventType;
use App\Audit\Export\AuditExporter;
use App\Audit\Models\AuditEvent;
use App\Audit\Support\HashChain;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class AuditExporterTest extends TestCase
{
    use RefreshDatabase;

    private function ledger(): AuditLedger
    {
        return app(AuditLedger::class);
    }

    private function actor(): User
    {
        return User::factory()->create();
    }

    private function employee(): Employee
    {
        return Employee::factory()->create();
    }

    private function record(
        AuditEventType $type,
        array $previous = [],
        array $new = [],
        ?User $actor = null,
        ?Model $auditable = null,
    ): AuditEvent {
        return $this->ledger()->record(
            eventType: $type,
            previousState: $previous,
            newState: $new,
            actor: $actor,
            auditable: $auditable,
        );
    }

    public function test_csv_has_header_and_contains_sorted_rows(): void
    {
        $user = $this->actor();
        $first = $this->employee();

        $this->record(AuditEventType::Created, [], ['name' => 'Mia'], $user, $first);
        $this->record(AuditEventType::Updated, ['name' => 'Mia'], ['name' => 'Milo'], $user, $first);

        $csv = app(AuditExporter::class)->csv();

        $lines = explode(PHP_EOL, trim($csv));
        $this->assertSame(
            'id,created_at,event_type,source,actor_user_id,actor_label,auditable_type,auditable_id,version,ip,user_agent,previous_state,new_state,prev_hash,hash',
            $lines[0],
        );
        // Neuestes Event zuerst (Sortierung id desc).
        $this->assertStringContainsString('updated', $lines[1]);
        $this->assertStringContainsString('created', $lines[2]);
    }

    public function test_csv_escapes_commas_and_quotes(): void
    {
        $user = $this->actor();
        $this->record(
            AuditEventType::Updated,
            ['name' => 'Mia, "die Zauberin"'],
            [],
            $user,
            $this->employee(),
        );

        $csv = app(AuditExporter::class)->csv();

        $lines = explode("\n", trim($csv));
        $row = str_getcsv($lines[1]);
        $this->assertSame('updated', $row[2]);
        $this->assertSame(json_encode(['name' => 'Mia, "die Zauberin"']), $row[11]);
    }

    public function test_json_contains_events_with_valid_chain_flag(): void
    {
        $user = $this->actor();
        $this->record(AuditEventType::Updated, ['name' => 'Mia'], ['name' => 'Milo'], $user, $this->employee());

        $decoded = json_decode(app(AuditExporter::class)->json(), true, flags: JSON_THROW_ON_ERROR);

        $event = $decoded['events'][0];
        $this->assertStringContainsString('updated', (string) $event['event_type']);
        $this->assertTrue($event['chain_valid']);
        $this->assertSame(64, strlen((string) $event['hash']));
        $this->assertArrayHasKey('generated_at', $decoded);
    }

    public function test_json_flags_tampered_event_as_invalid(): void
    {
        $user = $this->actor();
        // System-Events (ohne auditable): kein Trait-Rauschen, exakte Kontrolle.
        $first = $this->record(AuditEventType::Created, [], ['name' => 'Mia'], $user);
        $this->record(AuditEventType::Updated, ['name' => 'Mia'], ['name' => 'Milo'], $user);

        // DB-Trigger gezielt umgehen (rohes Update), Kette muss den Eingriff erkennen.
        $this->withoutAuditGuards(function () use ($first): void {
            DB::table('audit_events')->where('id', $first->id)->update(['new_state' => json_encode(['name' => 'Betrüger'])]);
        });

        $this->record(AuditEventType::StatusChanged, ['status' => 'x'], ['status' => 'y'], $user);

        $decoded = json_decode(app(AuditExporter::class)->json(), true, flags: JSON_THROW_ON_ERROR);

        // Neuestes zuerst: events[0] = StatusChanged (unangetastet), events[1] = Updated,
        // events[2] = Created (manipuliert, Kette bricht).
        $this->assertTrue($decoded['events'][0]['chain_valid']);
        $this->assertTrue($decoded['events'][1]['chain_valid']);
        $this->assertFalse($decoded['events'][2]['chain_valid']);
    }

    public function test_json_flags_followup_block_when_middle_block_hash_was_replaced(): void
    {
        $user = $this->actor();
        $this->record(AuditEventType::Created, [], ['name' => 'Mia'], $user);
        $middle = $this->record(AuditEventType::Updated, ['name' => 'Mia'], ['name' => 'Milo'], $user);
        $this->record(AuditEventType::StatusChanged, ['status' => 'x'], ['status' => 'y'], $user);

        // Angriff auf den Mittel-Block: Inhalt UND Hash werden konsistent neu
        // berechnet (Recompute allein erkennt das nicht mehr).
        $middle->new_state = ['name' => 'Betrüger'];
        $replacedHash = HashChain::hash($middle->prev_hash, $middle->blockPayload());

        $this->withoutAuditGuards(function () use ($middle, $replacedHash): void {
            DB::table('audit_events')
                ->where('id', $middle->id)
                ->update([
                    'new_state' => json_encode(['name' => 'Betrüger']),
                    'hash' => $replacedHash,
                ]);
        });

        $decoded = json_decode(app(AuditExporter::class)->json(), true, flags: JSON_THROW_ON_ERROR);

        // Neuestes zuerst: events[0] = StatusChanged (Linkage zum Mittel-Block
        // gebrochen → false), events[1] = Updated (selbst konsistent → true),
        // events[2] = Created (unverändert → true).
        $this->assertFalse($decoded['events'][0]['chain_valid']);
        $this->assertTrue($decoded['events'][1]['chain_valid']);
        $this->assertTrue($decoded['events'][2]['chain_valid']);
    }

    public function test_json_flags_reparented_block_with_consistent_hash(): void
    {
        $user = $this->actor();
        $this->record(AuditEventType::Created, [], ['name' => 'Mia'], $user);
        $middle = $this->record(AuditEventType::Updated, ['name' => 'Mia'], ['name' => 'Milo'], $user);
        $this->record(AuditEventType::StatusChanged, ['status' => 'x'], ['status' => 'y'], $user);

        // Re-Parenting: prev_hash auf null setzen und den Hash dazu konsistent
        // neu berechnen. Der Recompute allein wäre gültig – die strenge
        // Nachbarschaftsprüfung muss den Eingriff dennoch erkennen.
        $middle->prev_hash = null;

        $this->withoutAuditGuards(function () use ($middle): void {
            DB::table('audit_events')->where('id', $middle->id)->update([
                'prev_hash' => null,
                'hash' => HashChain::hash(null, $middle->blockPayload()),
            ]);
        });

        $decoded = json_decode(app(AuditExporter::class)->json(), true, flags: JSON_THROW_ON_ERROR);

        // events[1] = Updated (re-parented): Recompute ok, Nachbarschaft gebrochen.
        $this->assertFalse($decoded['events'][1]['chain_valid']);
    }

    public function test_json_flags_tampered_timestamp(): void
    {
        $user = $this->actor();
        $event = $this->record(AuditEventType::Created, [], ['name' => 'Mia'], $user);

        // created_at ist Teil des Hash-Payloads: eine Änderung bricht die Kette.
        $this->withoutAuditGuards(function () use ($event): void {
            DB::table('audit_events')->where('id', $event->id)->update(['created_at' => '2020-01-01 00:00:00']);
        });

        $decoded = json_decode(app(AuditExporter::class)->json(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertFalse($decoded['events'][0]['chain_valid']);
    }

    public function test_filters_by_event_type(): void
    {
        $user = $this->actor();
        $this->record(AuditEventType::Created, [], ['name' => 'Mia'], $user);
        $this->record(AuditEventType::Updated, ['name' => 'Mia'], ['name' => 'Milo'], $user);

        $exporter = app(AuditExporter::class);

        $lines = explode(PHP_EOL, trim($exporter->csv(['event_type' => AuditEventType::Updated->value])));
        $this->assertCount(2, $lines); // Header + eine Zeile
        $this->assertStringContainsString('updated', $lines[1]);

        // Auch Enum-Instanz als Filter.
        $json = json_decode($exporter->json(['event_type' => AuditEventType::Created]), true, flags: JSON_THROW_ON_ERROR);
        $this->assertCount(1, $json['events']);
    }

    public function test_filters_by_actor(): void
    {
        $alice = $this->actor();
        $bob = $this->actor();
        $employee = $this->employee();
        $this->record(AuditEventType::Created, [], ['name' => 'Mia'], $alice, $employee);
        $this->record(AuditEventType::Updated, ['name' => 'Mia'], ['name' => 'Milo'], $bob, $employee);

        $csv = app(AuditExporter::class)->csv(['actor' => $alice->id]);

        $this->assertStringNotContainsString('updated', $csv);
        $this->assertStringContainsString('created', $csv);
    }

    public function test_filters_by_date_range(): void
    {
        $user = $this->actor();
        $old = $this->record(AuditEventType::Created, [], ['name' => 'Mia'], $user);
        $this->record(AuditEventType::Updated, ['name' => 'Mia'], ['name' => 'Milo'], $user);

        $this->withoutAuditGuards(function () use ($old): void {
            DB::table('audit_events')->where('id', $old->id)->update(['created_at' => '2026-01-15 10:00:00']);
        });

        $exporter = app(AuditExporter::class);

        $fromOnly = explode(PHP_EOL, trim($exporter->csv(['from' => '2026-01-16'])));
        $this->assertCount(2, $fromOnly);
        $this->assertStringNotContainsString('created', $fromOnly[1]);

        $toOnly = explode(PHP_EOL, trim($exporter->csv(['to' => '2026-01-15', 'from' => '2026-01-14'])));
        $this->assertCount(2, $toOnly);
        $this->assertStringContainsString('created', $toOnly[1]);
    }
}
