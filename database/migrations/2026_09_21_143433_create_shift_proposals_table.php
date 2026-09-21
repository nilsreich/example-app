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
        Schema::create('shift_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('optimization_id')->constrained('shift_optimizations')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            // KI-Konfidenz 0-100; steuert Badge-Farbe im Dispatching-UI.
            $table->unsignedTinyInteger('score');
            // Explainable AI: Warum passt dieser Mitarbeiter?
            $table->json('match_reasons')->nullable();
            // Trade-offs: Was spricht dagegen bzw. was kostet es?
            $table->json('risks_or_tradeoffs')->nullable();
            // Vorformulierter, editierbarer Benachrichtigungstext (SMS/WhatsApp).
            $table->text('draft_message')->nullable();
            $table->timestamps();

            $table->index(['optimization_id', 'score']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_proposals');
    }
};
