<?php

namespace Tests\Unit\Identity;

use App\Identity\EntraGroupRoleMapper;
use PHPUnit\Framework\TestCase;

final class EntraGroupRoleMapperTest extends TestCase
{
    public function test_maps_group_object_id_to_role_and_department(): void
    {
        $mapper = new EntraGroupRoleMapper([
            'group-a' => ['role' => 'web-admin', 'department' => 'IT'],
        ]);

        $result = $mapper->map(['group-a']);

        $this->assertSame('web-admin', $result['role']);
        $this->assertSame('IT', $result['department']);
    }

    public function test_picks_first_matching_config_entry(): void
    {
        $mapper = new EntraGroupRoleMapper([
            'group-a' => ['role' => 'web-admin', 'department' => 'IT'],
            'group-b' => ['role' => 'bereichsleiter', 'department' => 'Logistik'],
        ]);

        // Die Token-Reihenfolge ist irrelevant: Die erste in der Config
        // definierte Gruppe gewinnt (deterministische Präzedenz).
        $result = $mapper->map(['group-x', 'group-b', 'group-a']);

        $this->assertSame('web-admin', $result['role']);
        $this->assertSame('IT', $result['department']);
    }

    public function test_denies_when_no_group_matches_and_no_fallback(): void
    {
        $mapper = new EntraGroupRoleMapper([
            'group-a' => ['role' => 'web-admin', 'department' => 'IT'],
        ]);

        $result = $mapper->map(['group-unknown']);

        $this->assertNull($result['role']);
        $this->assertNull($result['department']);
    }

    public function test_fallback_role_applies_when_nothing_matches(): void
    {
        $mapper = new EntraGroupRoleMapper(
            ['group-a' => ['role' => 'web-admin', 'department' => 'IT']],
            fallbackRole: 'nutzer',
        );

        $result = $mapper->map(['group-unknown']);

        $this->assertSame('nutzer', $result['role']);
        $this->assertNull($result['department']);
    }

    public function test_matching_group_wins_over_fallback(): void
    {
        $mapper = new EntraGroupRoleMapper(
            ['group-a' => ['role' => 'web-admin', 'department' => 'IT']],
            fallbackRole: 'nutzer',
        );

        $result = $mapper->map(['group-a']);

        $this->assertSame('web-admin', $result['role']);
    }

    public function test_denies_empty_group_list_without_fallback(): void
    {
        $mapper = new EntraGroupRoleMapper([
            'group-a' => ['role' => 'web-admin', 'department' => 'IT'],
        ]);

        $result = $mapper->map([]);

        $this->assertNull($result['role']);
    }
}
