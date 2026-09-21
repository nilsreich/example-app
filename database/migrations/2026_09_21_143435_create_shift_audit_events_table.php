<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Forward-only Ledger: Zustandsänderungen werden ausschließlich als neue
        // Events angehängt (inkl. Rollback als eigenes Event), niemals per DELETE.
        Schema::create('shift_audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
            // Monoton steigende Versionsnummer je Schicht (Vergabe im Service, transaktional).
            $table->unsignedInteger('version');
            $table->string('event_type', 30);
            $table->json('previous_state')->nullable();
            $table->json('new_state')->nullable();
            // Verweis auf das stornierte Event (nur bei Rollbacks gesetzt).
            $table->foreignId('reverted_event_id')->nullable()->constrained('shift_audit_events')->nullOnDelete();
            // Ledger-Semantik: nur created_at, kein updated_at.
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['shift_id', 'version']);
            $table->index(['shift_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_audit_events');
    }
};
