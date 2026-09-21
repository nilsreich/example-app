<?php

namespace App\Services;

use App\Enums\AuditEventType;
use App\Enums\ShiftStatus;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftAuditEvent;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Weist einen Mitarbeiter zu und protokolliert die Änderung revisionssicher.
 * Erstzuweisung (offen → besetzt) vs. manuelle Umbesetzung wird automatisch
 * am Vorzustand erkannt – kein impliziter Modus nötig.
 */
class ShiftAssignmentService
{
    public function __construct(
        private readonly ShiftAuditLedger $ledger,
    ) {}

    public function assign(Shift $shift, Employee $employee, ?string $note = null): ShiftAuditEvent
    {
        if (! $employee->is_active) {
            throw new InvalidArgumentException('Inaktive Mitarbeiter können keiner Schicht zugewiesen werden.');
        }

        return DB::transaction(function () use ($shift, $employee, $note): ShiftAuditEvent {
            $shift = Shift::whereKey($shift->id)->lockForUpdate()->firstOrFail();

            if ($shift->status === ShiftStatus::Cancelled) {
                throw new InvalidArgumentException('Stornierte Schichten können nicht zugewiesen werden.');
            }

            $eventType = $shift->status === ShiftStatus::Open && $shift->assigned_employee_id === null
                ? AuditEventType::InitialAssignment
                : AuditEventType::ManualOverride;

            $previousState = $shift->snapshot();
            $shift->update([
                'assigned_employee_id' => $employee->id,
                'status' => ShiftStatus::Assigned,
            ]);

            $newState = $shift->fresh()->snapshot();
            if ($note !== null && $note !== '') {
                $newState['note'] = $note;
            }

            return $this->ledger->record($shift, $eventType, $previousState, $newState);
        });
    }
}
