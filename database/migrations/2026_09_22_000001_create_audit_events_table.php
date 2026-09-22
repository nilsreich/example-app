<?php

use App\Audit\Support\AuditGuards;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('auditable');
            // Bewusst OHNE FK-Constraint: Das Ledger ist append-only (DB-Trigger
            // verhindern jedes UPDATE/DELETE). Ein FK kollidiert damit – nullOnDelete
            // würde eine Ledger-Zeile updaten (Trigger-Abbruch), restrictOnDelete
            // würde die Kontolöschung sperren. Die Akteur-Identität bleibt über das
            // zum Schreibzeitpunkt denormalisierte, unveränderliche actor_label
            // nachvollziehbar (statt über die FK-Spalte im Hash-Payload).
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('actor_label')->nullable();
            $table->string('event_type', 50);
            $table->json('previous_state')->nullable();
            $table->json('new_state')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->string('source', 20)->default('web');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->char('prev_hash', 64)->nullable();
            $table->char('hash', 64);
            $table->timestamp('created_at')->nullable();
            $table->index('event_type');
            $table->index('created_at');
            // Version ist pro Entität eindeutig (verhindert doppelte Ledger-Versionen).
            $table->unique(['auditable_type', 'auditable_id', 'version'], 'audit_events_entity_version_unique');
        });

        // Append-only zusätzlich auf DB-Ebene erzwingen.
        AuditGuards::enable();
    }

    public function down(): void
    {
        // GoBD: Die Ledger-Historie wird bewusst nicht automatisch entfernt.
        // Ein Rollback würde die revisionssichere Nachweiskette vernichten.
        throw new RuntimeException('Die Audit-Ledger-Migration kann nicht zurückgerollt werden (GoBD).');
    }
};
