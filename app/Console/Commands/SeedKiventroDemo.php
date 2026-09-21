<?php

namespace App\Console\Commands;

use App\Enums\ShiftStatus;
use App\Models\Employee;
use App\Models\Shift;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

#[Signature('db:seed-kiventro-demo')]
#[Description('Lädt das kiventro Demo-Szenario: 10 Mitarbeiter, 1 Krankmeldung, 3 kritische Schichten (setzt Demo-Daten zurück)')]
class SeedKiventroDemo extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->resetDemoTables();

        $now = now();
        $tomorrow = $now->copy()->addDay()->startOfDay();

        // 10 Mitarbeiter mit gestaffelten Skills, Ruhezeiten und Überstunden.
        // Zeiten relativ zu "jetzt", damit die Demo an jedem Tag funktioniert.
        $employees = [
            ['Anna Berger', 'Schichtleiterin', 'Logistik', ['Schichtleitung', 'Staplerschein'], 45, $now->copy()->subHours(20)],
            ['Ben Kramer', 'Staplerfahrer', 'Logistik', ['Staplerschein'], 300, $now->copy()->subHours(8)],
            ['Cem Yilmaz', 'Kommissionierer', 'Logistik', [], 0, $now->copy()->subHours(40)],
            ['Dora Lehmann', 'Staplerfahrerin', 'Logistik', ['Staplerschein', 'Ersthelfer'], 120, $now->copy()->subHours(12)],
            ['Erik Sommer', 'Kommissionierer', 'Logistik', ['Ersthelfer'], 600, $now->copy()->subHours(18)],
            ['Fatma Demir', 'Produktionshelferin', 'Produktion', [], 30, $now->copy()->subHours(18)],
            ['Gregor Hahn', 'Schichtleiter', 'Produktion', ['Schichtleitung', 'Ersthelfer'], 90, $now->copy()->subHours(8)],
            ['Hanna Vogt', 'Versandmitarbeiterin', 'Versand', ['ADR-Schein'], 0, null],
            ['Ivan Petrov', 'Versandmitarbeiter', 'Versand', ['Staplerschein'], 200, $now->copy()->subHours(12)],
            // Krankmeldung: löst das Demo-Szenario "kurzfristiger Personalausfall" aus.
            ['Julia Brandt', 'Kommissioniererin', 'Logistik', ['Staplerschein'], 60, $now->copy()->subHours(30), false],
        ];

        foreach ($employees as $entry) {
            [$name, $role, $department, $qualifications, $overtime, $lastEnded] = $entry;
            $active = $entry[6] ?? true;

            Employee::create([
                'name' => $name,
                'role' => $role,
                'department' => $department,
                'qualifications' => $qualifications,
                'weekly_overtime_minutes' => $overtime,
                'last_shift_ended_at' => $lastEnded,
                'is_active' => $active,
            ]);
        }

        // 3 kritisch unbesetzte Schichten (morgen).
        $shifts = [
            ['Frühschicht Logistik', $tomorrow->copy()->setTime(6, 0), $tomorrow->copy()->setTime(14, 0), 'Logistik', ['Staplerschein']],
            ['Spätschicht Produktion', $tomorrow->copy()->setTime(14, 0), $tomorrow->copy()->setTime(22, 0), 'Produktion', ['Ersthelfer']],
            ['Nachtschicht Versand', $tomorrow->copy()->setTime(22, 0), $tomorrow->copy()->addDay()->setTime(6, 0), 'Versand', ['Staplerschein']],
        ];

        foreach ($shifts as [$title, $startsAt, $endsAt, $department, $required]) {
            Shift::create([
                'title' => $title,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'department' => $department,
                'required_qualifications' => $required,
                'status' => ShiftStatus::Open,
            ]);
        }

        $this->info('kiventro Demo-Szenario geladen: 10 Mitarbeiter (1 krank), 3 offene Schichten.');

        return self::SUCCESS;
    }

    /**
     * Setzt NUR die Demo-Domäne zurück (User, Settings und AI-SDK-Tabellen bleiben bestehen).
     */
    private function resetDemoTables(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach (['shift_feedbacks', 'shift_proposals', 'shift_optimizations', 'shift_audit_events', 'shifts', 'employees'] as $table) {
            DB::table($table)->delete();
        }

        Schema::enableForeignKeyConstraints();
    }
}
