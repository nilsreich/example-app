<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Auth\Pages\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Rollenbasierter Einstieg: Jede Rolle landet nach dem Login direkt
 * auf ihrer Arbeitsfläche (schnelle, eindeutige Wege).
 */
class RoleLoginRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_nutzer_lands_on_my_shifts(): void
    {
        $user = User::factory()->nutzer()->create(['password' => 'secret-password']);

        Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'secret-password'])
            ->call('authenticate')
            ->assertRedirect(route('filament.admin.pages.my-shifts'));
    }

    public function test_bereichsleiter_lands_on_shift_list(): void
    {
        $user = User::factory()->bereichsleiter('Logistik')->create(['password' => 'secret-password']);

        Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'secret-password'])
            ->call('authenticate')
            ->assertRedirect(route('filament.admin.resources.shifts.index'));
    }

    public function test_geschaeftsfuehrer_lands_on_dashboard(): void
    {
        $user = User::factory()->create(['role' => UserRole::Geschaeftsfuehrer, 'password' => 'secret-password']);

        Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'secret-password'])
            ->call('authenticate')
            ->assertRedirect(route('filament.admin.pages.dashboard'));
    }

    public function test_web_admin_lands_on_dashboard(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'secret-password'])
            ->call('authenticate')
            ->assertRedirect(route('filament.admin.pages.dashboard'));
    }
}
