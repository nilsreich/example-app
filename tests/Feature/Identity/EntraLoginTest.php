<?php

namespace Tests\Feature\Identity;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class EntraLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('entra.enabled', true);

        config()->set('services.microsoft', [
            'client_id' => 'test-client-id',
            'client_secret' => 'test-client-secret',
            'redirect' => 'http://localhost/auth/entra/callback',
            'tenant' => 'tenant-123',
        ]);
    }

    public function test_redirect_forwards_to_microsoft_entra_login(): void
    {
        $response = $this->get('/auth/entra');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'https://login.microsoftonline.com/tenant-123/oauth2/v2.0/authorize',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_callback_provisions_new_user_and_authenticates(): void
    {
        config()->set('entra.group_mapping', [
            'group-a' => ['role' => UserRole::Nutzer->value, 'department' => 'IT'],
        ]);
        Socialite::fake('microsoft', $this->fakeEntraUser(groups: ['group-a']));

        $this->get('/auth/entra/callback')
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'max@example.test',
            'entra_object_id' => 'entra-object-id-123',
            'department' => 'IT',
        ]);
    }

    public function test_callback_matches_existing_user_by_entra_object_id(): void
    {
        config()->set('entra.group_mapping', [
            'group-a' => ['role' => UserRole::Nutzer->value, 'department' => null],
        ]);
        $existing = User::factory()->create([
            'entra_object_id' => 'entra-object-id-123',
            'email' => 'alt@example.test',
            'role' => UserRole::Nutzer,
        ]);
        Socialite::fake('microsoft', $this->fakeEntraUser(email: 'neu@example.test', groups: ['group-a']));

        $this->get('/auth/entra/callback')
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($existing);
        $this->assertSame('neu@example.test', $existing->fresh()->email);
    }

    public function test_callback_denies_user_without_matching_group(): void
    {
        Socialite::fake('microsoft', $this->fakeEntraUser(groups: ['group-x']));

        $this->get('/auth/entra/callback')
            ->assertRedirect(route('login', absolute: false))
            ->assertSessionHas('error');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['entra_object_id' => 'entra-object-id-123']);
    }

    public function test_routes_are_disabled_when_entra_is_disabled(): void
    {
        config()->set('entra.enabled', false);

        $this->get('/auth/entra')->assertNotFound();
        $this->get('/auth/entra/callback')->assertNotFound();
    }

    public function test_callback_returns_to_login_when_provider_fails(): void
    {
        Socialite::shouldReceive('driver->user')->andThrow(new \RuntimeException('access_denied'));

        $this->get('/auth/entra/callback')
            ->assertRedirect(route('login', absolute: false))
            ->assertSessionHas('error');

        $this->assertGuest();
    }

    private function fakeEntraUser(string $email = 'max@example.test', array $groups = []): SocialiteUser
    {
        $user = new SocialiteUser;
        $user->map([
            'id' => 'entra-object-id-123',
            'name' => 'Max Mustermann',
            'email' => $email,
        ]);
        $user->setRaw(['groups' => $groups]);

        return $user;
    }
}
