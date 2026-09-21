<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rechtematrix der Rollen-Sichten: Jede Rolle erreicht ihre Seiten –
 * und nur ihre. Schützt die Demo davor, falsche Sichten zu zeigen.
 */
class RoleAccessMatrixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{User, array<string, int>}>
     */
    private function matrix(): array
    {
        return [
            'web-admin' => [
                User::factory()->create(),
                ['/admin' => 200, '/admin/shifts' => 200, '/admin/pipeline-settings' => 200, '/admin/feedback-settings' => 200, '/admin/feedback-reports' => 200, '/admin/my-shifts' => 200],
            ],
            'geschaeftsfuehrer' => [
                User::factory()->create(['role' => UserRole::Geschaeftsfuehrer]),
                ['/admin' => 200, '/admin/shifts' => 200, '/admin/pipeline-settings' => 403, '/admin/feedback-settings' => 403, '/admin/feedback-reports' => 403, '/admin/my-shifts' => 403],
            ],
            'bereichsleiter' => [
                User::factory()->bereichsleiter('Logistik')->create(),
                ['/admin' => 200, '/admin/shifts' => 200, '/admin/pipeline-settings' => 403, '/admin/feedback-settings' => 403, '/admin/feedback-reports' => 403, '/admin/my-shifts' => 403],
            ],
            'nutzer' => [
                User::factory()->nutzer()->create(),
                ['/admin' => 200, '/admin/shifts' => 403, '/admin/pipeline-settings' => 403, '/admin/feedback-settings' => 403, '/admin/feedback-reports' => 403, '/admin/my-shifts' => 200],
            ],
        ];
    }

    public function test_each_role_only_reaches_its_pages(): void
    {
        foreach ($this->matrix() as $role => [$user, $expectations]) {
            $this->actingAs($user);

            foreach ($expectations as $path => $status) {
                $this->get($path)->assertStatus($status, "Rolle {$role} auf {$path}");
            }
        }
    }

    public function test_bereichsleiter_sees_only_own_department_shifts(): void
    {
        Shift::factory()->create(['title' => 'Logistik-Schicht', 'department' => 'Logistik']);
        Shift::factory()->create(['title' => 'Produktions-Schicht', 'department' => 'Produktion']);

        $this->actingAs(User::factory()->bereichsleiter('Logistik')->create());

        $this->get('/admin/shifts')
            ->assertOk()
            ->assertSee('Logistik-Schicht')
            ->assertDontSee('Produktions-Schicht');
    }

    public function test_web_admin_sees_all_departments(): void
    {
        Shift::factory()->create(['title' => 'Logistik-Schicht', 'department' => 'Logistik']);
        Shift::factory()->create(['title' => 'Produktions-Schicht', 'department' => 'Produktion']);

        $this->actingAs(User::factory()->create());

        $this->get('/admin/shifts')
            ->assertOk()
            ->assertSee('Logistik-Schicht')
            ->assertSee('Produktions-Schicht');
    }

    public function test_dashboard_adapts_ctas_per_role(): void
    {
        // Bereichsleiter: Dispositions-CTA.
        $this->actingAs(User::factory()->bereichsleiter('Logistik')->create());
        $this->get('/admin')->assertOk()->assertSee('disponieren');

        // Mitarbeiter: Self-Service-CTA, keine ROI-Kennzahlen.
        $this->actingAs(User::factory()->nutzer()->create());
        $this->get('/admin')
            ->assertOk()
            ->assertSee('Meine Schichten &amp; Verfügbarkeit', escape: false)
            ->assertDontSee('Eingesparte Disponentenkosten');

        // Geschäftsführung: ROI-Kennzahlen, aber read-only (kein Dispositions-CTA).
        $this->actingAs(User::factory()->create(['role' => UserRole::Geschaeftsfuehrer]));
        $this->get('/admin')
            ->assertOk()
            ->assertSee('Eingesparte Disponentenkosten')
            ->assertDontSee('disponieren');
    }
}
