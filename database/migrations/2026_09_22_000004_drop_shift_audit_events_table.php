<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Die Demo-eigene Ledger-Tabelle wird entfernt: Zuweisungs-Historie liegt
     * jetzt vollständig im generischen audit-Ledger (audit_events). Ein
     * Fehlstart nach der Umstellung bleibt möglich (expliziter Drop, kein
     * stilles Löschen), daher nur nach erfolgreicher Migration auf T12.
     *
     * Guard: Läuft die Migration auf einer Bestandsinstallation, in der die
     * Alt-Tabelle noch Daten führt, bricht sie ab, statt die Historie zu
     * zerstören (GoBD). Erst nach manueller Prüfung/Bestätigung fortfahren.
     */
    public function up(): void
    {
        if (Schema::hasTable('shift_audit_events') && DB::table('shift_audit_events')->exists()) {
            throw new RuntimeException(
                'shift_audit_events enthält noch Datensätze. Historische Zuweisungs-Events sind '
                .'bereits im audit-Ledger (audit_events) angelegt? Sonst erst migrieren/sichern '
                .'(scripts/backup.sh), dann die Tabelle manuell leeren und die Migration erneut ausführen.'
            );
        }

        Schema::dropIfExists('shift_audit_events');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('shift_audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('event_type', 30);
            $table->json('previous_state')->nullable();
            $table->json('new_state')->nullable();
            $table->foreignId('reverted_event_id')->nullable()->constrained('shift_audit_events')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['shift_id', 'version']);
            $table->index(['shift_id', 'created_at']);
        });
    }
};
