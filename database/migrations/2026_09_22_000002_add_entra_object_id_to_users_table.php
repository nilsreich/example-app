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
        Schema::table('users', function (Blueprint $table) {
            // Stabile Zuordnung Entra-Konto -> User über Objekt-Id (sub/oid),
            // unabhängig von E-Mail-Wechseln. Unique: mehrere NULL erlaubt (MySQL 8.4/SQLite).
            $table->string('entra_object_id')->nullable()->after('id');
            $table->unique('entra_object_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['entra_object_id']);
            $table->dropColumn('entra_object_id');
        });
    }
};
