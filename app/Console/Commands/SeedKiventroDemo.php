<?php

namespace App\Console\Commands;

use App\Enums\FeedbackRating;
use App\Enums\ShiftStatus;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftFeedback;
use App\Models\User;
use App\Services\ShiftAssignmentService;
use App\Services\ShiftOptimizationRunner;
use Carbon\CarbonInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

#[Signature('db:seed-kiventro-demo {--with-history : Zusätzlich 2 Wochen Vorgeschichte (Zuweisungen + Feedbacks) für volle ROI-Dashboards}')]
#[Description('Lädt das kiventro Demo-Szenario: 10 Mitarbeiter, 1 Krankmeldung, 3 kritische Schichten, Demo-User (setzt Demo-Daten zurück)')]
class SeedKiventroDemo extends Command
{
    /**
     * Demo-Zugangsdaten (öffentlich dokumentiert, nur für die Demo-Umgebung).
     */
    public const DEMO_PASSWORD = 'kiventro-demo';

    /**
     * Execute the console command.
     */
    public function handle(ShiftOptimizationRunner $runner, ShiftAssignmentService $assignments): int
    {
        $this->resetDemoTables();
        $this->seedEmployees();
        $this->seedOpenShifts();
        $this->seedDemoUsers();

        $message = 'kiventro Demo-Szenario geladen: 10 Mitarbeiter (1 krank), 3 offene Schichten.';

        if ($this->option('with-history')) {
            $this->seedHistory($runner, $assignments);
            $message .= ' Plus 14 Tage Vorgeschichte (4 Zuweisungen, 3 Feedbacks).';
        }

        $this->info($message);
        $this->info('Demo-Login: admin@kiventro.de / disponent@kiventro.de, Passwort: '.self::DEMO_PASSWORD);

        return self::SUCCESS;
    }

    private function seedEmployees(): void
    {
        $now = now();

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

            Employee::create([
                'name' => $name,
                'role' => $role,
                'department' => $department,
                'qualifications' => $qualifications,
                'weekly_overtime_minutes' => $overtime,
                'last_shift_ended_at' => $lastEnded,
                'is_active' => $entry[6] ?? true,
            ]);
        }
    }

    private function seedOpenShifts(): void
    {
        $tomorrow = now()->addDay()->startOfDay();

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
    }

    /**
     * Demo-User per Upsert (eigene Konten überleben einen Demo-Reset).
     */
    private function seedDemoUsers(): void
    {
        foreach ([
            ['Kiventro Admin', 'admin@kiventro.de', 'admin'],
            ['Tom Dispatch', 'disponent@kiventro.de', 'disponent'],
        ] as [$name, $email, $role]) {
            User::updateOrCreate(
                ['email' => $email],
                ['name' => $name, 'role' => $role, 'password' => self::DEMO_PASSWORD],
            );
        }
    }

    /**
     * Deterministische Vorgeschichte über echte Code-Pfade (Runner + Services),
     * damit ROI-Dashboard und Charts sofort Daten zeigen:
     * 4 Zuweisungen (3× Top-Match, 1× Override), 2× positiv, 1× negativ.
     */
    private function seedHistory(ShiftOptimizationRunner $runner, ShiftAssignmentService $assignments): void
    {
        $entries = [
            // [Tage zurück, Titel, Abteilung, Qualifikation, Top-Match übernehmen?, Feedback]
            [12, 'Frühschicht Logistik (KW)', 'Logistik', ['Staplerschein'], true, FeedbackRating::Positive],
            [9, 'Spätschicht Produktion (KW)', 'Produktion', ['Ersthelfer'], true, FeedbackRating::Positive],
            [5, 'Nachtschicht Versand (KW)', 'Versand', ['Staplerschein'], true, FeedbackRating::Negative],
            [2, 'Frühschicht Logistik (KW)', 'Logistik', ['Staplerschein'], false, null],
        ];

        foreach ($entries as [$daysAgo, $title, $department, $required, $takeTop, $feedbackRating]) {
            $day = now()->subDays($daysAgo)->startOfDay();

            $shift = Shift::create([
                'title' => $title,
                'starts_at' => $day->copy()->setTime(6, 0),
                'ends_at' => $day->copy()->setTime(14, 0),
                'department' => $department,
                'required_qualifications' => $required,
                'status' => ShiftStatus::Open,
            ]);

            $optimization = $runner->run($shift);
            $proposals = $optimization->proposals;
            $proposal = $takeTop ? $proposals->first() : ($proposals->skip(1)->first() ?? $proposals->first());
            $event = $assignments->assign($shift, $proposal->employee);

            $models = [$optimization, $event, ...$proposals->all()];
            $feedback = null;

            if ($feedbackRating !== null) {
                $feedback = ShiftFeedback::create([
                    'proposal_id' => $proposal->id,
                    'rating' => $feedbackRating,
                    'reason_category' => $feedbackRating === FeedbackRating::Negative ? 'Regelkonflikt' : null,
                    'comment' => $feedbackRating === FeedbackRating::Negative ? 'Demo: Ruhezeit grenzwertig.' : 'Demo: Reibungslos übernommen.',
                ]);
                $models[] = $feedback;
            }

            // Vorgeschichte auf das Schichtdatum zurückdatieren (Charts gruppieren nach Tag).
            foreach ($models as $model) {
                $this->backdate($model, $day);
            }
        }
    }

    private function backdate(Model $model, CarbonInterface $date): void
    {
        // Ledger-Tabellen (z. B. shift_audit_events) haben kein updated_at.
        $attributes = ['created_at' => $date];

        if (
            $model->getUpdatedAtColumn() !== null
            && in_array($model->getUpdatedAtColumn(), Schema::getColumnListing($model->getTable()), true)
        ) {
            $attributes['updated_at'] = $date;
        }

        $model->timestamps = false;
        $model->forceFill($attributes)->save();
        $model->timestamps = true;
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
