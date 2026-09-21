<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftAuditEvent;
use App\Models\ShiftFeedback;
use App\Models\User;
use App\Services\RoiCalculatorService;
use App\Services\RoiMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class KiventroDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_base_scenario_seeds_team_shifts_and_demo_users(): void
    {
        Artisan::call('db:seed-kiventro-demo');

        $this->assertSame(10, Employee::count());
        $this->assertSame(9, Employee::available()->count());
        $this->assertSame(3, Shift::open()->count());
        // Ein Demo-Login je Rolle.
        $this->assertSame(UserRole::WebAdmin, User::where('email', 'admin@kiventro.de')->first()->role);
        $this->assertSame(UserRole::Geschaeftsfuehrer, User::where('email', 'gf@kiventro.de')->first()->role);
        $this->assertSame('Logistik', User::where('email', 'leitung.logistik@kiventro.de')->first()->department);
        $this->assertSame(UserRole::Nutzer, User::where('email', 'mitarbeiter@kiventro.de')->first()->role);
        // Self-Service: Mitarbeiter-Login ist mit Personal-Datensatz und Schicht verknüpft.
        $this->assertNotNull(User::where('email', 'mitarbeiter@kiventro.de')->first()->employee);
        $this->assertSame(1, User::where('email', 'mitarbeiter@kiventro.de')->first()->employee->shifts()->count());
        // Offene Schichten kommen mit vorberechnetem Top-Match (1-Klick-Demo).
        foreach (Shift::open()->get() as $shift) {
            $this->assertNotNull($shift->latestOptimization, "Lauf fehlt für {$shift->title}");
            $this->assertNotNull($shift->latestOptimization->topProposal, "Top-Match fehlt für {$shift->title}");
        }
        // Demo-User sind für /dashboard (verified-Middleware) freigeschaltet.
        $this->assertTrue(User::where('email', 'admin@kiventro.de')->first()->hasVerifiedEmail());
    }

    public function test_history_option_fills_roi_dashboard(): void
    {
        Artisan::call('db:seed-kiventro-demo', ['--with-history' => true]);

        $metrics = new RoiMetricsService(new RoiCalculatorService);

        $this->assertSame(4, $metrics->resolvedConflicts());
        // 4 Konflikte × 45 Min × 65 €/h = 195 €.
        $this->assertSame(195.0, $metrics->savedCostsEur());
        // 3 Top-Übernahmen, davon 1 mit Negativ-Feedback → 2/4 ohne Nacharbeit.
        $this->assertSame(50.0, $metrics->automationRate());
        $this->assertSame(3, ShiftFeedback::count());
        $this->assertSame(4, ShiftAuditEvent::count());
        $this->assertNotNull($metrics->averageConfidence());
    }

    public function test_reseeding_preserves_personal_accounts(): void
    {
        User::factory()->create(['email' => 'ich@beispiel.de']);

        Artisan::call('db:seed-kiventro-demo');

        $this->assertNotNull(User::where('email', 'ich@beispiel.de')->first());
        $this->assertSame(10, Employee::count());
    }
}
