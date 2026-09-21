<?php

namespace App\Services;

use App\Enums\AuditEventType;
use App\Enums\ShiftStatus;
use App\Models\Employee;
use App\Models\Shift;
use Illuminate\Support\Facades\DB;

/**
 * Self-Service-Verfügbarkeit: Meldet ein Mitarbeiter sich krank, werden alle
 * künftig zugewiesenen Schichten wieder offen und jede Änderung wird als
 * Ledger-Event protokolliert (identische Revisionskette wie im Dispatching).
 */
class EmployeeAvailabilityService
{
    public function __construct(
        private readonly ShiftAuditLedger $ledger,
    ) {}

    /**
     * Krankmeldung: deaktiviert den Mitarbeiter für die Kandidatenauswahl und
     * gibt künftige Schichten zur Neu-Disposition frei.
     *
     * @return int Anzahl der freigegebenen Schichten.
     */
    public function reportSick(Employee $employee, ?string $reason = null): int
    {
        return DB::transaction(function () use ($employee, $reason): int {
            $employee->update(['is_active' => false]);

            $released = 0;

            $shifts = Shift::where('assigned_employee_id', $employee->id)
                ->where('starts_at', '>=', now())
                ->where('status', ShiftStatus::Assigned)
                ->orderBy('starts_at')
                ->lockForUpdate()
                ->get();

            foreach ($shifts as $shift) {
                $previousState = $shift->snapshot();
                $shift->update([
                    'assigned_employee_id' => null,
                    'status' => ShiftStatus::Open,
                ]);

                $newState = $shift->fresh()->snapshot();
                $newState['availability_reason'] = $reason ?? 'Krankmeldung durch Mitarbeiter';
                $newState['released_employee_id'] = $employee->id;

                $this->ledger->record($shift, AuditEventType::AvailabilityReported, $previousState, $newState);
                $released++;
            }

            return $released;
        });
    }

    /**
     * Rückmeldung "wieder verfügbar": nimmt den Mitarbeiter zurück in den Kandidatenpool.
     */
    public function reportAvailable(Employee $employee): void
    {
        $employee->update(['is_active' => true]);
    }
}
