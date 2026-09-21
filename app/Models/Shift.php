<?php

namespace App\Models;

use App\Enums\ShiftStatus;
use Database\Factories\ShiftFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Zu besetzende Schicht mit Qualifikationsanforderungen und Zuweisungsstatus.
 *
 * @property int $id
 * @property string $title
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property string $department
 * @property array<int, string>|null $required_qualifications
 * @property ShiftStatus $status
 * @property int|null $assigned_employee_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['title', 'starts_at', 'ends_at', 'department', 'required_qualifications', 'status', 'assigned_employee_id'])]
class Shift extends Model
{
    /** @use HasFactory<ShiftFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'required_qualifications' => 'array',
            'status' => ShiftStatus::class,
        ];
    }

    /**
     * Aktuell zugewiesener Mitarbeiter (null = unbesetzt).
     */
    public function assignedEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_employee_id');
    }

    /**
     * Alle Optimierungsläufe (Mock + Live) zu dieser Schicht, neueste zuerst.
     */
    public function optimizations(): HasMany
    {
        return $this->hasMany(ShiftOptimization::class)->latest();
    }

    /**
     * Vollständiger Forward-Ledger aller Zustandsänderungen, chronologisch.
     */
    public function auditEvents(): HasMany
    {
        return $this->hasMany(ShiftAuditEvent::class)->orderBy('version');
    }

    /**
     * Offene, zu disponierende Schichten.
     */
    #[Scope]
    protected function open(Builder $query): Builder
    {
        return $query->where('status', ShiftStatus::Open);
    }

    /**
     * Kompakter Dispatching-Snapshot für Pipeline-Payload und Audit-Events.
     *
     * @return array{shift_id: int, title: string, department: string, starts_at: string, ends_at: string, status: string, required_qualifications: array<int, string>, assigned_employee_id: int|null}
     */
    public function snapshot(): array
    {
        return [
            'shift_id' => $this->id,
            'title' => $this->title,
            'department' => $this->department,
            'starts_at' => $this->starts_at->toIso8601String(),
            'ends_at' => $this->ends_at->toIso8601String(),
            'status' => $this->status->value,
            'required_qualifications' => $this->required_qualifications ?? [],
            'assigned_employee_id' => $this->assigned_employee_id,
        ];
    }
}
