<?php

namespace App\Identity;

/**
 * Mappt Entra-Gruppen-Object-Ids auf Rolle/Abteilung (config-gesteuert).
 *
 * Kein Default-Rollen-Verfall: Ohne Treffer und ohne Fallback-Rolle wird
 * der Login abgelehnt (role === null).
 */
final readonly class EntraGroupRoleMapper
{
    /**
     * @param  array<string, array{role: string, department: string|null}>  $mapping
     */
    public function __construct(
        private array $mapping,
        private ?string $fallbackRole = null,
    ) {}

    /**
     * Liefert das Mapping der ersten passenden Gruppe (Config-Reihenfolge).
     *
     * @param  list<string>  $groupObjectIds  Gruppen-Object-Ids aus dem Entra-ID-Token
     * @return array{role: string|null, department: string|null}
     */
    public function map(array $groupObjectIds): array
    {
        foreach ($groupObjectIds as $objectId) {
            if (isset($this->mapping[$objectId])) {
                return $this->mapping[$objectId];
            }
        }

        if ($this->fallbackRole !== null) {
            return ['role' => $this->fallbackRole, 'department' => null];
        }

        return ['role' => null, 'department' => null];
    }
}
