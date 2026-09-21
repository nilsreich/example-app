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
        Schema::create('shift_optimizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
            // Welcher Pipeline-Treiber den Lauf erzeugt hat: "mock" oder "live".
            $table->string('driver_used', 10);
            // Exakter KI-Kontext (Anfrage-Payload) zur Nachvollziehbarkeit.
            $table->json('prompt_payload');
            // Rohantwort des Treibers (Mock-Fixture bzw. AI-SDK-Response).
            $table->json('raw_response')->nullable();
            $table->unsignedInteger('execution_time_ms')->nullable();
            $table->unsignedInteger('tokens_used')->nullable();
            // Geschätzte Inferenzkosten in EUR (nur Live-Treiber > 0).
            $table->decimal('cost_estimate_eur', 10, 6)->nullable();
            $table->timestamps();

            $table->index(['shift_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_optimizations');
    }
};
