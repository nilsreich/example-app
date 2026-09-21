<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Shift;

/**
 * Baut den identischen KI-Kontext für Mock- und Live-Treiber.
 * Single Source of Truth für Prompt-Payload (Prompt ist revisionssicher
 * in shift_optimizations.prompt_payload abgelegt).
 */
class ShiftCandidateContextBuilder
{
    /**
     * @return array{shift: array<string, mixed>, required_qualifications: array<int, string>, candidates: list<array<string, mixed>>, generated_at: string}
     */
    public function for(Shift $shift): array
    {
        $candidates = Employee::available()
            ->orderBy('name')
            ->get()
            ->map(fn (Employee $employee): array => [
                'employee_id' => $employee->id,
                'name' => $employee->name,
                'role' => $employee->role,
                'department' => $employee->department,
                'qualifications' => $employee->qualifications ?? [],
                'weekly_overtime_minutes' => $employee->weekly_overtime_minutes,
                'rest_hours' => $employee->restHours($shift->starts_at),
                'last_shift_ended_at' => $employee->last_shift_ended_at?->toIso8601String(),
            ])
            ->all();

        return [
            'shift' => $shift->snapshot(),
            'required_qualifications' => $shift->required_qualifications ?? [],
            'candidates' => $candidates,
            'generated_at' => now()->toIso8601String(),
        ];
    }
}
