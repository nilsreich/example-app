<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_dashboard_loads_with_widgets(): void
    {
        $this->actingAsAdmin();

        $this->get('/admin')->assertOk();
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

    public function test_unknown_role_is_denied_panel_access(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'gast']));

        $this->get('/admin/shifts')->assertForbidden();
    }

    public function test_admin_and_disponent_roles_can_access_panel(): void
    {
        foreach (['admin', 'disponent'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));

            $this->get('/admin/shifts')->assertOk();
        }
    }
}
