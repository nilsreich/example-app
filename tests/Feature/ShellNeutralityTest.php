<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShellNeutralityTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_neutral(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertDontSee('kiventro')
            ->assertDontSee('Demo-Zugänge')
            ->assertDontSee('admin@kiventro.de')
            ->assertDontSee('kiventro-demo');
    }

    public function test_dashboard_heading_carries_no_vendor_branding(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Dispatch-Cockpit')
            ->assertDontSee('kiventro Dispatch-Cockpit');
    }

    public function test_welcome_page_is_neutral(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('kiventro');
    }

    public function test_welcome_has_no_vendor_links(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('laravel.com')
            ->assertDontSee('laracasts.com')
            ->assertDontSee('cloud.laravel.com')
            ->assertDontSee("Let's get started");
    }

    public function test_app_shell_has_no_starter_kit_links(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('livewire-starter-kit')
            ->assertDontSee('starter-kits');
    }

    public function test_layout_templates_are_vendor_free(): void
    {
        foreach (['header', 'sidebar'] as $layout) {
            $template = file_get_contents(resource_path("views/layouts/app/{$layout}.blade.php"));

            $this->assertStringNotContainsString('github.com/laravel', $template);
            $this->assertStringNotContainsString('laravel.com/docs/starter-kits', $template);
        }
    }

    public function test_env_example_ships_neutral_app_name(): void
    {
        $env = file_get_contents(base_path('.env.example'));

        $this->assertStringContainsString('APP_NAME="B2E-Template"', $env);
    }
}
