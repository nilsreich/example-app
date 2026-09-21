<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Abteilung für die Bereichsleiter-Sicht (null = abteilungsübergreifend).
            $table->string('department', 50)->nullable()->after('role');
        });

        // Bestehende Demo-Rollen auf das neue Modell heben.
        DB::table('users')->where('role', 'admin')->update(['role' => 'web-admin']);
        DB::table('users')->where('role', 'disponent')->update(['role' => 'bereichsleiter', 'department' => 'Logistik']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('users')->where('role', 'web-admin')->update(['role' => 'admin']);
        DB::table('users')->where('role', 'bereichsleiter')->update(['role' => 'disponent']);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('department');
        });
    }
};
