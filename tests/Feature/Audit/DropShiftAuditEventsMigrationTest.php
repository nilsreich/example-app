<?php

namespace Tests\Feature\Audit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * Review-Befund (Required): Die Drop-Migration für `shift_audit_events` darf
 * auf Bestandsinstallationen keine Ledger-Historie still zerstören – Guard:
 * Abbruch, sobald Daten in der Alt-Tabelle liegen.
 */
final class DropShiftAuditEventsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_09_22_000004_drop_shift_audit_events_table.php');
    }

    private function createLegacyTable(): void
    {
        Schema::create('shift_audit_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shift_id');
            $table->unsignedInteger('version');
            $table->string('event_type', 30);
            $table->json('previous_state')->nullable();
            $table->json('new_state')->nullable();
            $table->unsignedBigInteger('reverted_event_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function test_drop_is_aborted_when_legacy_table_still_contains_rows(): void
    {
        $this->createLegacyTable();
        // Bestehende Historie simuliert der Migration ab, die Daten stehen im Weg.
        DB::table('shift_audit_events')->insert([
            'shift_id' => 1,
            'version' => 1,
            'event_type' => 'assignment',
            'created_at' => now(),
        ]);

        $this->expectException(RuntimeException::class);
        $this->migration()->up();
    }

    public function test_drop_succeeds_when_legacy_table_is_empty(): void
    {
        $this->createLegacyTable();

        $this->migration()->up();

        $this->assertFalse(Schema::hasTable('shift_audit_events'));
    }
}
