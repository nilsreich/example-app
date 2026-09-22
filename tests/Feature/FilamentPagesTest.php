<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Shifts\Pages\ListShifts;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FilamentPagesTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/shifts')->assertRedirect('/admin/login');
    }

    public function test_login_page_is_neutral(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertDontSee('Demo-Zugänge')
            ->assertDontSee('admin@kiventro.de')
            ->assertDontSee('mitarbeiter@kiventro.de')
            ->assertDontSee('kiventro-demo');
    }

    public function test_dashboard_loads_with_widgets(): void
    {
        $this->actingAsAdmin();

        // Non-lazy Widgets: Kennzahlen müssen direkt im HTML stehen (auch ohne JS).
        $this->get('/admin')
            ->assertOk()
            ->assertSee('Web-Administrator')
            ->assertSee('Eingesparte Disponentenkosten')
            ->assertSee('Automatisierungsquote')
            ->assertSee('Ø Match-Konfidenz')
            ->assertSee('Kosten- & Zeiteinsparung')
            ->assertSee('Schichtstatus-Verteilung')
            ->assertSee('Feedback zu KI-Vorschlägen');
    }

    public function test_shift_list_page_loads(): void
    {
        $this->actingAsAdmin();

        $this->get('/admin/shifts')->assertOk();
    }

    public function test_pipeline_settings_page_loads(): void
    {
        $this->actingAsAdmin();

        $this->get('/admin/pipeline-settings')->assertOk();
    }

    public function test_feedback_pages_load(): void
    {
        $this->actingAsAdmin();

        $this->get('/admin/feedback-reports')->assertOk();
        $this->get('/admin/feedback-settings')->assertOk()->assertSee('In-App-Feedback');
    }

    public function test_all_roles_can_access_the_panel_dashboard(): void
    {
        $roles = [
            User::factory()->create(),                        // Web-Admin
            User::factory()->create(['role' => 'geschaeftsfuehrer']),
            User::factory()->bereichsleiter('Logistik')->create(),
            User::factory()->nutzer()->create(),
        ];

        foreach ($roles as $user) {
            $this->actingAs($user);

            $this->get('/admin')->assertOk();
        }
    }

    public function test_demo_reset_action_is_admin_only(): void
    {
        // Read-only-Rolle (GF) darf den destruktiven Demo-Reset nicht auslösen …
        Livewire::actingAs(User::factory()->create(['role' => UserRole::Geschaeftsfuehrer]))
            ->test(ListShifts::class)
            ->assertActionHidden('seedDemo');

        // … die System-Verwaltung schon.
        Livewire::actingAs(User::factory()->create())
            ->test(ListShifts::class)
            ->assertActionVisible('seedDemo');
    }
}
