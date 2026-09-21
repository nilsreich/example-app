<?php

namespace Tests\Feature;

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
        $this->assertSame('admin', User::where('email', 'admin@kiventro.de')->value('role'));
        $this->assertSame('disponent', User::where('email', 'disponent@kiventro.de')->value('role'));
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
