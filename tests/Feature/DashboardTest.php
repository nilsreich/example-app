<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_dashboard_shows_kpis_and_dispatch_links(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Dispatch-Cockpit');
        $response->assertDontSee('kiventro Dispatch-Cockpit');
        $response->assertSee('Eingesparte Disponentenkosten');
        $response->assertSee('Automatisierungsquote');
        $response->assertSee('Ø Match-Konfidenz');
        $response->assertSee('Offene Schichten');
        $response->assertSee('/admin/shifts', escape: false);
    }
}
