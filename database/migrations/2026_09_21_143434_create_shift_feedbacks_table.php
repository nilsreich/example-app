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
        Schema::create('shift_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained('shift_proposals')->cascadeOnDelete();
            // Daumen hoch/runter zur KI-Qualität dieses Vorschlags.
            $table->string('rating', 10);
            // Pflicht bei "negative": Regelkonflikt / Mitarbeiter nicht erreichbar / Sonstiges.
            $table->string('reason_category')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['proposal_id', 'rating']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_feedbacks');
    }
};
