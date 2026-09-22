<?php

namespace Tests\Feature\Identity;

use App\Enums\UserRole;
use Tests\TestCase;

class EntraConfigTest extends TestCase
{
    public function test_roles_default_set_contains_the_four_template_roles(): void
    {
        $roles = config('entra.roles');

        $this->assertSame([
            UserRole::WebAdmin->value,
            UserRole::Geschaeftsfuehrer->value,
            UserRole::Bereichsleiter->value,
            UserRole::Nutzer->value,
        ], $roles);
    }

    public function test_fallback_role_is_null_by_default(): void
    {
        $this->assertNull(config('entra.fallback_role'));
    }

    public function test_group_mapping_is_empty_by_default(): void
    {
        $this->assertSame([], config('entra.group_mapping'));
    }
}
