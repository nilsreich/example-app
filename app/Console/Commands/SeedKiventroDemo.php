<?php

namespace App\Console\Commands;

use App\Enums\FeedbackRating;
use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Feedback\Enums\FeedbackCategory;
use App\Feedback\Enums\FeedbackStatus;
use App\Feedback\Models\FeedbackReport;
use App\Models\Employee;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\ShiftFeedback;
use App\Models\User;
use App\Services\ShiftAssignmentService;
use App\Services\ShiftOptimizationRunner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;
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
        $this->seedOpenShifts($runner);
        $this->seedDemoUsers();
        $this->seedFeedbackInbox();

        $message = 'kiventro Demo-Szenario geladen: 10 Mitarbeiter (1 krank), 3 offene Schichten, 2 Feedback-Meldungen.';

        if ($this->option('with-history')) {
            $this->seedHistory($runner, $assignments);
            $message .= ' Plus 14 Tage Vorgeschichte (4 Zuweisungen, 3 Feedbacks).';
        }

        $this->info($message);
        $this->info('Demo-Logins: admin@kiventro.de (Web-Admin), gf@kiventro.de (GF), leitung.logistik@kiventro.de (Bereichsleiter), mitarbeiter@kiventro.de (Mitarbeiter), Passwort: '.self::DEMO_PASSWORD);

        return self::SUCCESS;
    }

    private function seedEmployees(): void
    {
        $now = now();

        // Zeiten relativ zu "jetzt", damit die Demo an jedem Tag funktioniert.
        $employees = [
            ['Anna Berger', 'Schichtleiterin', 'Logistik', ['Schichtleitung', 'Staplerschein'], 45, $now->copy()->subHours(20), true],
            ['Ben Kramer', 'Staplerfahrer', 'Logistik', ['Staplerschein'], 300, $now->copy()->subHours(8), true],
            ['Cem Yilmaz', 'Kommissionierer', 'Logistik', [], 0, $now->copy()->subHours(40), true],
            ['Dora Lehmann', 'Staplerfahrerin', 'Logistik', ['Staplerschein', 'Ersthelfer'], 120, $now->copy()->subHours(12), true],
            ['Erik Sommer', 'Kommissionierer', 'Logistik', ['Ersthelfer'], 600, $now->copy()->subHours(18), true],
            ['Fatma Demir', 'Produktionshelferin', 'Produktion', [], 30, $now->copy()->subHours(18), true],
            ['Gregor Hahn', 'Schichtleiter', 'Produktion', ['Schichtleitung', 'Ersthelfer'], 90, $now->copy()->subHours(8), true],
            ['Hanna Vogt', 'Versandmitarbeiterin', 'Versand', ['ADR-Schein'], 0, null, true],
            ['Ivan Petrov', 'Versandmitarbeiter', 'Versand', ['Staplerschein'], 200, $now->copy()->subHours(12), true],
            // Krankmeldung: löst das Demo-Szenario "kurzfristiger Personalausfall" aus.
            ['Julia Brandt', 'Kommissioniererin', 'Logistik', ['Staplerschein'], 60, $now->copy()->subHours(30), false],
        ];

        foreach ($employees as [$name, $role, $department, $qualifications, $overtime, $lastEnded, $isActive]) {
            Employee::create([
                'name' => $name,
                'role' => $role,
                'department' => $department,
                'qualifications' => $qualifications,
                'weekly_overtime_minutes' => $overtime,
                'last_shift_ended_at' => $lastEnded,
                'is_active' => $isActive,
            ]);
        }
    }

    private function seedOpenShifts(ShiftOptimizationRunner $runner): void
    {
        $tomorrow = now()->addDay()->startOfDay();

        // 3 kritisch unbesetzte Schichten (morgen).
        $shifts = [
            ['Frühschicht Logistik', $tomorrow->copy()->setTime(6, 0), $tomorrow->copy()->setTime(14, 0), 'Logistik', ['Staplerschein']],
            ['Spätschicht Produktion', $tomorrow->copy()->setTime(14, 0), $tomorrow->copy()->setTime(22, 0), 'Produktion', ['Ersthelfer']],
            ['Nachtschicht Versand', $tomorrow->copy()->setTime(22, 0), $tomorrow->copy()->addDay()->setTime(6, 0), 'Versand', ['Staplerschein']],
        ];

        foreach ($shifts as [$title, $startsAt, $endsAt, $department, $required]) {
            $shift = Shift::create([
                'title' => $title,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'department' => $department,
                'required_qualifications' => $required,
                'status' => ShiftStatus::Open,
            ]);

            // Vorberechneter Lauf: Die Schichtenliste zeigt sofort das Top-Match
            // inklusive 1-Klick-Übernahme (besserer Demo-Einstieg).
            $runner->run($shift);
        }
    }

    /**
     * Demo-User per Upsert (eigene Konten überleben einen Demo-Reset).
     * Ein Login je Rolle, damit jede Sicht direkt vorführbar ist.
     */
    private function seedDemoUsers(): void
    {
        foreach ([
            ['Kiventro Admin', 'admin@kiventro.de', UserRole::WebAdmin, null],
            ['Vera Kessler', 'gf@kiventro.de', UserRole::Geschaeftsfuehrer, null],
            ['Lars Neumann', 'leitung.logistik@kiventro.de', UserRole::Bereichsleiter, 'Logistik'],
            ['Petra Sommer', 'leitung.produktion@kiventro.de', UserRole::Bereichsleiter, 'Produktion'],
            ['Murat Aksoy', 'leitung.versand@kiventro.de', UserRole::Bereichsleiter, 'Versand'],
            ['Ben Kramer', 'mitarbeiter@kiventro.de', UserRole::Nutzer, 'Logistik'],
        ] as [$name, $email, $role, $department]) {
            $user = User::updateOrCreate(
                ['email' => $email],
                ['name' => $name, 'role' => $role, 'department' => $department, 'password' => self::DEMO_PASSWORD],
            );

            // E-Mail gilt als bestätigt: Demo-User sollen direkt ins /dashboard
            // (verified-Middleware) und ins Admin-Panel kommen.
            if (! $user->hasVerifiedEmail()) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }
        }

        $this->seedEmployeeSelfService();
    }

    /**
     * Verknüpft den Mitarbeiter-Login mit seinem Personal-Datensatz und gibt
     * ihm eine künftige Schicht, damit "Meine Schichten" sofort etwas zeigt
     * und die Krankmeldung den Dispositions-Workflow auslösen kann.
     */
    private function seedEmployeeSelfService(): void
    {
        $user = User::where('email', 'mitarbeiter@kiventro.de')->first();
        $employee = Employee::where('name', 'Ben Kramer')->first();

        if (! $user || ! $employee) {
            return;
        }

        $employee->update(['user_id' => $user->id]);

        $tomorrow = now()->addDay()->startOfDay();

        Shift::create([
            'title' => 'Spätschicht Logistik (Self-Service)',
            'starts_at' => $tomorrow->copy()->setTime(14, 0),
            'ends_at' => $tomorrow->copy()->setTime(22, 0),
            'department' => 'Logistik',
            'required_qualifications' => ['Staplerschein'],
            'status' => ShiftStatus::Assigned,
            'assigned_employee_id' => $employee->id,
        ]);
    }

    /**
     * Aktiviert das In-App-Feedback-Widget für die Demo und legt zwei
     * Beispielmeldungen an (Triage-Eingang ist damit sichtbar).
     */
    private function seedFeedbackInbox(): void
    {
        Setting::set(Setting::FEEDBACK_WIDGET_ENABLED, '1');

        $admin = User::where('email', 'admin@kiventro.de')->first();
        $dispatcher = User::where('email', 'leitung.logistik@kiventro.de')->first();

        FeedbackReport::create([
            'user_id' => $dispatcher->id ?? $admin->id,
            'category' => FeedbackCategory::Bug,
            'message' => 'Beim Rollback fehlte die Storno-Nachricht in der Bestätigung. Bitte prüfen, ob das Feld übernommen wird.',
            'page_url' => url('/admin/shifts'),
            'page_title' => 'Schichten',
            'element_selector' => 'div.fi-ta-ctn > table',
            'element_text' => 'Zuweisung zurückrollen',
            'browser_info' => ['userAgent' => 'Demo', 'language' => 'de-DE', 'viewport' => '1440×900'],
            'status' => FeedbackStatus::New,
        ]);

        FeedbackReport::create([
            'user_id' => $admin->id ?? $dispatcher->id,
            'category' => FeedbackCategory::Idea,
            'message' => 'Vorschlag: Konfidenz-Score im Slide-Over zusätzlich als Trendpfeil im Vergleich zum letzten Lauf zeigen.',
            'page_url' => url('/admin/shifts'),
            'page_title' => 'Schichten',
            'browser_info' => ['userAgent' => 'Demo', 'language' => 'de-DE', 'viewport' => '1440×900'],
            'status' => FeedbackStatus::InProgress,
            'resolution_note' => 'Im Backlog für Sprint 2 vorgemerkt.',
        ]);
    }

    /**
     * Deterministische Vorgeschichte über echte Code-Pfade (Runner + Services),
     * damit ROI-Dashboard und Charts sofort Daten zeigen:
     * 4 Zuweisungen (3× Top-Match, 1× Override), 2× positiv, 1× negativ.
     */
    private function seedHistory(ShiftOptimizationRunner $runner, ShiftAssignmentService $assignments): void
    {
        /** @var list<array{int, string, string, list<string>, bool, FeedbackRating|null}> $entries */
        $entries = [
            // [Tage zurück, Titel, Abteilung, Qualifikation, Top-Match übernehmen?, Feedback]
            [12, 'Frühschicht Logistik (KW)', 'Logistik', ['Staplerschein'], true, FeedbackRating::Positive],
            [9, 'Spätschicht Produktion (KW)', 'Produktion', ['Ersthelfer'], true, FeedbackRating::Positive],
            [5, 'Nachtschicht Versand (KW)', 'Versand', ['Staplerschein'], true, FeedbackRating::Negative],
            [2, 'Frühschicht Logistik (KW)', 'Logistik', ['Staplerschein'], false, null],
        ];

        $base = now();

        foreach ($entries as [$daysAgo, $title, $department, $required, $takeTop, $feedbackRating]) {
            // Historische Zeitachse: now() wird auf den Schichttag fixiert, damit
            // Modelle UND Ledger-Events (created_at steckt im Hash-Payload) von
            // vornherein mit dem korrekten Datum entstehen – kein Nachtrag-Update,
            // das mit dem append-only-Trigger kollidieren würde.
            $day = $base->copy()->subDays($daysAgo)->startOfDay();
            Date::setTestNow($day);

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

            if ($proposal === null || $proposal->employee === null) {
                continue;
            }

            $assignments->assign($shift, $proposal->employee);

            if ($feedbackRating !== null) {
                ShiftFeedback::create([
                    'proposal_id' => $proposal->id,
                    'rating' => $feedbackRating,
                    'reason_category' => $feedbackRating === FeedbackRating::Negative ? 'Regelkonflikt' : null,
                    'comment' => $feedbackRating === FeedbackRating::Negative ? 'Demo: Ruhezeit grenzwertig.' : 'Demo: Reibungslos übernommen.',
                ]);
            }

            Date::setTestNow();
        }
    }

    /**
     * Setzt NUR die Demo-Domäne zurück (User, Settings und AI-SDK-Tabellen bleiben bestehen).
     */
    private function resetDemoTables(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach (['feedback_reports', 'shift_feedbacks', 'shift_proposals', 'shift_optimizations', 'shifts', 'employees'] as $table) {
            DB::table($table)->delete();
        }

        Schema::enableForeignKeyConstraints();
    }
}
