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

        config()->set('entra.enabled', true);

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

    public function test_sso_login_records_role_and_department_changes_as_audit_events(): void
    {
        // Bereits per Entra gebundenes Konto – Rolle und Abteilung weichen ab.
        $user = User::factory()->create([
            'entra_object_id' => 'entra-object-id-123',
            'email' => 'max@example.test',
            'role' => UserRole::Geschaeftsfuehrer,
            'department' => 'Vertrieb',
        ]);
        config()->set('entra.group_mapping', [
            'group-a' => ['role' => UserRole::Nutzer->value, 'department' => 'Logistik'],
        ]);
        Socialite::fake('microsoft', $this->fakeEntraUser());

        $this->get('/auth/entra/callback')
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'role_changed',
            'actor_user_id' => $user->id,
            'source' => 'entra',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'department_changed',
            'actor_user_id' => $user->id,
            'source' => 'entra',
        ]);
        $this->assertSame(UserRole::Nutzer, $user->fresh()->role);
        $this->assertSame('Logistik', $user->fresh()->department);
    }

    public function test_sso_login_with_email_of_account_bound_elsewhere_is_denied(): void
    {
        // E-Mail gehört zu einem Konto, das bereits an eine andere Entra-Identität gebunden ist.
        $user = User::factory()->create([
            'entra_object_id' => 'andere-object-id',
            'email' => 'max@example.test',
            'role' => UserRole::Nutzer,
        ]);
        Socialite::fake('microsoft', $this->fakeEntraUser());

        $this->get('/auth/entra/callback')
            ->assertRedirect(route('login', absolute: false));

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'login_failed',
            'source' => 'entra',
        ]);
        // Bindung bleibt unverändert.
        $this->assertSame('andere-object-id', $user->fresh()->entra_object_id);
    }

    public function test_unchanged_role_and_department_record_no_change_events(): void
    {
        // Regression: getRawOriginal statt getOriginal – sonst erzeugt der
        // Enum-Cast bei jedem SSO-Login ein falsches RoleChanged-Event.
        $user = User::factory()->create([
            'entra_object_id' => 'entra-object-id-123',
            'email' => 'max@example.test',
            'role' => UserRole::Nutzer,
            'department' => 'IT',
        ]);
        Socialite::fake('microsoft', $this->fakeEntraUser());

        $this->get('/auth/entra/callback')
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertDatabaseMissing('audit_events', [
            'event_type' => 'role_changed',
            'actor_user_id' => $user->id,
        ]);
        $this->assertDatabaseMissing('audit_events', [
            'event_type' => 'department_changed',
            'actor_user_id' => $user->id,
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
