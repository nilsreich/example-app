<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Der Aufbewahrungslauf (feedback:retention) filtert ausschließlich nach
     * created_at – der bestehende Index (status, created_at) deckt das nicht ab.
     */
    public function up(): void
    {
        Schema::table('feedback_reports', function (Blueprint $table) {
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('feedback_reports', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });
    }
};
