<?php

namespace Tests\Feature\Identity;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class EntraAuditEventsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.microsoft', [
            'client_id' => 'test-client-id',
            'client_secret' => 'test-client-secret',
            'redirect' => 'http://localhost/auth/entra/callback',
            'tenant' => 'tenant-123',
        ]);
        config()->set('entra.group_mapping', [
            'group-a' => ['role' => UserRole::Nutzer->value, 'department' => 'IT'],
        ]);
    }

    public function test_successful_sso_login_records_login_event(): void
    {
        $user = User::factory()->create([
            'entra_object_id' => 'entra-object-id-123',
            'email' => 'max@example.test',
            'role' => UserRole::Nutzer,
        ]);
        Socialite::fake('microsoft', $this->fakeEntraUser());

        $this->get('/auth/entra/callback')
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'login',
            'source' => 'entra',
            'actor_user_id' => $user->id,
        ]);
    }

    public function test_first_login_records_provisioned_event(): void
    {
        Socialite::fake('microsoft', $this->fakeEntraUser());

        $this->get('/auth/entra/callback');

        $user = User::query()->where('entra_object_id', 'entra-object-id-123')->firstOrFail();

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'provisioned',
            'source' => 'entra',
            'actor_user_id' => $user->id,
            'auditable_type' => $user->getMorphClass(),
            'auditable_id' => $user->id,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'login',
            'source' => 'entra',
            'actor_user_id' => $user->id,
        ]);
    }

    public function test_denied_sso_login_records_login_failed_event(): void
    {
        Socialite::fake('microsoft', $this->fakeEntraUser(groups: ['group-x']));

        $this->get('/auth/entra/callback')
            ->assertRedirect(route('login', absolute: false));

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'login_failed',
            'source' => 'entra',
        ]);
    }

    public function test_logout_records_logout_event(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('logout'));

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'logout',
            'source' => 'web',
            'actor_user_id' => $user->id,
        ]);
    }

    private function fakeEntraUser(array $groups = ['group-a']): SocialiteUser
    {
        $user = new SocialiteUser;
        $user->map([
            'id' => 'entra-object-id-123',
            'name' => 'Max Mustermann',
            'email' => 'max@example.test',
        ]);
        $user->setRaw(['groups' => $groups]);

        return $user;
    }
}
