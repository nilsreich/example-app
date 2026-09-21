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
        Schema::create('feedback_reports', function (Blueprint $table) {
            $table->id();
            // Melder (Pflicht: In-App-Feedback nur für angemeldete Nutzer).
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category', 20);
            $table->text('message');
            // Kontext zur Reproduktion (lokal gespeichert, kein externer Dienst).
            $table->string('page_url', 2048);
            $table->string('page_title', 500)->nullable();
            $table->string('element_selector', 500)->nullable();
            $table->string('element_text', 500)->nullable();
            $table->json('browser_info')->nullable();
            // Screenshot liegt auf dem privaten "local"-Disk (storage/app/private).
            $table->string('screenshot_path', 2048)->nullable();
            $table->string('status', 20)->default('new');
            $table->text('resolution_note')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feedback_reports');
    }
};
