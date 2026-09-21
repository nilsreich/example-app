<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Mitarbeitender mit Qualifikationen, Überstundenkonto und Ruhezeit-Tracking.
 *
 * @property int $id
 * @property string $name
 * @property string $role
 * @property string $department
 * @property array<int, string>|null $qualifications
 * @property int $weekly_overtime_minutes
 * @property Carbon|null $last_shift_ended_at
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'role', 'department', 'qualifications', 'weekly_overtime_minutes', 'last_shift_ended_at', 'is_active'])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'qualifications' => 'array',
            'last_shift_ended_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Schichten, die diesem Mitarbeiter aktuell zugewiesen sind.
     */
    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class, 'assigned_employee_id');
    }

    /**
     * KI-Vorschläge, die diesen Mitarbeiter als Kandidaten nennen.
     */
    public function proposals(): HasMany
    {
        return $this->hasMany(ShiftProposal::class);
    }

    /**
     * Nur verfügbare (aktive) Mitarbeitende für die Kandidatenauswahl.
     */
    #[Scope]
    protected function available(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Prüft eine einzelne Qualifikation (exakter String-Match).
     */
    public function hasQualification(string $qualification): bool
    {
        return in_array($qualification, $this->qualifications ?? [], true);
    }

    /**
     * Fehlende Qualifikationen aus einer Anforderungsliste.
     *
     * @param  array<int, string>  $required
     * @return array<int, string>
     */
    public function missingQualifications(array $required): array
    {
        return array_values(array_diff($required, $this->qualifications ?? []));
    }

    /**
     * Ruhezeit in Stunden seit Ende der letzten Schicht.
     * Null = keine Vor-Schicht bekannt (maximale Flexibilität).
     * CarbonInterface deckt Carbon + CarbonImmutable ab (App nutzt Immutable).
     */
    public function restHours(?CarbonInterface $reference = null): ?float
    {
        if ($this->last_shift_ended_at === null) {
            return null;
        }

        return $this->last_shift_ended_at->floatDiffInHours($reference ?? now());
    }
}
