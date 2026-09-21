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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('role');
            $table->string('department');
            // Qualifikationen als JSON-Array, z. B. ["Staplerschein", "Ersthelfer"].
            $table->json('qualifications')->nullable();
            // Kumulierte Wochenüberstunden in Minuten (Integer statt Float für exakte ROI-/Regelberechnung).
            $table->unsignedInteger('weekly_overtime_minutes')->default(0);
            // Ende der zuletzt abgeschlossenen Schicht; Basis für Ruhezeit-Prüfung.
            $table->timestamp('last_shift_ended_at')->nullable();
            // Verfügbarkeitsschalter (Krankheit/Urlaub) für die Kandidatenauswahl.
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['department', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
