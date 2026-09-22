<?php

namespace App\Services;

use App\Audit\AuditLedger;
use App\Audit\Enums\AuditEventType;
use App\Audit\Models\AuditEvent;
use App\Enums\ShiftStatus;
use App\Models\Employee;
use App\Models\Shift;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Weist einen Mitarbeiter zu und protokolliert die Änderung revisionssicher
 * im generischen Audit-Ledger. Erstzuweisung (offen → besetzt) vs. manuelle
 * Umbesetzung wird automatisch am Vorzustand erkannt – kein impliziter Modus.
 */
class ShiftAssignmentService
{
    public function __construct(
        private readonly AuditLedger $ledger,
    ) {}

    public function assign(Shift $shift, Employee $employee, ?string $note = null): AuditEvent
    {
        return DB::transaction(function () use ($shift, $employee, $note): AuditEvent {
            $shift = Shift::whereKey($shift->id)->lockForUpdate()->firstOrFail();

            // Mitarbeiter unter demselben Lock laden: paralleles Deaktivieren soll
            // die Zuweisung nicht durchrutschen lassen.
            $employee = Employee::whereKey($employee->id)->lockForUpdate()->firstOrFail();

            if (! $employee->is_active) {
                throw new InvalidArgumentException('Inaktive Mitarbeiter können keiner Schicht zugewiesen werden.');
            }

            // Pflichtqualifikationen sind verbindlich, nicht nur Teil des Scores:
            // eine Zuweisung ohne sie würde die Domänenregel der Schicht verletzen.
            $missing = $employee->missingQualifications($shift->required_qualifications ?? []);

            if ($missing !== []) {
                throw new InvalidArgumentException(sprintf(
                    '%s fehlt die Pflichtqualifikation: %s.',
                    $employee->name,
                    implode(', ', $missing),
                ));
            }

            if ($shift->status === ShiftStatus::Cancelled) {
                throw new InvalidArgumentException('Stornierte Schichten können nicht zugewiesen werden.');
            }

            $eventType = $shift->status === ShiftStatus::Open && $shift->assigned_employee_id === null
                ? AuditEventType::InitialAssignment
                : AuditEventType::ManualOverride;

            $previousState = $shift->snapshot();
            $shift->assigned_employee_id = $employee->id;
            $shift->status = ShiftStatus::Assigned;
            $shift->save();

            $newState = $shift->fresh()->snapshot();
            if ($note !== null && $note !== '') {
                $newState['note'] = $note;
            }

            return $this->ledger->record(
                eventType: $eventType,
                previousState: $previousState,
                newState: $newState,
                auditable: $shift,
            );
        });
    }
}
