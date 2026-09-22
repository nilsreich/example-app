<?php

namespace Tests\Feature\Identity;

use App\Enums\UserRole;
use App\Identity\EntraGroupRoleMapper;
use App\Identity\EntraUserResolver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class EntraUserResolverTest extends TestCase
{
    use RefreshDatabase;

    private function resolver(array $mapping = [], ?string $fallbackRole = null): EntraUserResolver
    {
        return new EntraUserResolver(
            new EntraGroupRoleMapper($mapping, $fallbackRole),
        );
    }

    private function entraUser(string $objectId, string $email, string $name = 'Max Mustermann'): SocialiteUser
    {
        $user = new SocialiteUser;
        $user->map([
            'id' => $objectId,
            'name' => $name,
            'email' => $email,
        ]);

        return $user;
    }

    public function test_provisions_new_user_with_mapped_role_and_department(): void
    {
        $resolver = $this->resolver(['group-a' => ['role' => 'web-admin', 'department' => 'IT']]);

        $user = $resolver->resolve($this->entraUser('obj-1', 'max@example.test'), ['group-a']);

        $this->assertNotNull($user);
        $this->assertSame('obj-1', $user->entra_object_id);
        $this->assertSame('max@example.test', $user->email);
        $this->assertSame(UserRole::WebAdmin, $user->role);
        $this->assertSame('IT', $user->department);
        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseHas('users', ['entra_object_id' => 'obj-1', 'email' => 'max@example.test']);
    }

    public function test_matches_existing_user_by_entra_object_id_across_email_change(): void
    {
        $resolver = $this->resolver(['group-a' => ['role' => 'nutzer', 'department' => null]]);
        $existing = User::factory()->create([
            'entra_object_id' => 'obj-1',
            'email' => 'alt@example.test',
            'role' => UserRole::Nutzer,
        ]);

        $user = $resolver->resolve($this->entraUser('obj-1', 'neu@example.test'), ['group-a']);

        $this->assertSame($existing->id, $user->id);
        $this->assertSame('neu@example.test', $user->email);
    }

    public function test_adopts_existing_local_user_by_email_and_sets_entra_object_id(): void
    {
        $resolver = $this->resolver(['group-a' => ['role' => 'nutzer', 'department' => null]]);
        $existing = User::factory()->create([
            'entra_object_id' => null,
            'email' => 'max@example.test',
        ]);

        $user = $resolver->resolve($this->entraUser('obj-9', 'max@example.test'), ['group-a']);

        $this->assertSame($existing->id, $user->id);
        $this->assertSame('obj-9', $user->entra_object_id);
    }

    public function test_does_not_rebind_existing_account_to_foreign_object_id(): void
    {
        $resolver = $this->resolver(['group-a' => ['role' => 'nutzer', 'department' => null]]);
        $existing = User::factory()->create([
            'entra_object_id' => 'obj-gültig',
            'email' => 'max@example.test',
        ]);

        // Gleiche E-Mail, aber eine andere (fremde) Entra-Object-Id: Der Login
        // darf das bestehende Konto nicht an die fremde Identität binden.
        $user = $resolver->resolve($this->entraUser('obj-fremd', 'max@example.test'), ['group-a']);

        $this->assertNull($user);
        $this->assertDatabaseHas('users', [
            'id' => $existing->id,
            'entra_object_id' => 'obj-gültig',
        ]);
    }

    public function test_denies_user_without_matching_group(): void
    {
        $resolver = $this->resolver(['group-a' => ['role' => 'web-admin', 'department' => 'IT']]);

        $user = $resolver->resolve($this->entraUser('obj-2', 'fremd@example.test'), ['group-x']);

        $this->assertNull($user);
        $this->assertDatabaseMissing('users', ['entra_object_id' => 'obj-2']);
    }

    public function test_group_mapping_uses_config_order_not_token_order(): void
    {
        $resolver = $this->resolver([
            'group-high' => ['role' => 'web-admin', 'department' => 'IT'],
            'group-low' => ['role' => 'nutzer', 'department' => null],
        ]);

        // Token-Reihenfolge niedrig→hoch, Config-Reihenfolge hoch→niedrig:
        // Die erste konfigurierte Gruppe gewinnt (deterministische Präzedenz).
        $user = $resolver->resolve($this->entraUser('obj-3', 'max@example.test'), ['group-low', 'group-high']);

        $this->assertNotNull($user);
        $this->assertSame(UserRole::WebAdmin, $user->role);
    }

    public function test_denies_user_when_mapped_role_is_invalid(): void
    {
        $resolver = $this->resolver(['group-a' => ['role' => 'kein-gueltiger-wert', 'department' => null]]);

        $user = $resolver->resolve($this->entraUser('obj-4', 'max@example.test'), ['group-a']);

        $this->assertNull($user);
        $this->assertDatabaseMissing('users', ['entra_object_id' => 'obj-4']);
    }
}
