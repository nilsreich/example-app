<?php

namespace App\Services;

use App\Enums\AuditEventType;
use App\Enums\ShiftStatus;
use App\Models\Shift;
use App\Models\ShiftAuditEvent;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Forward-Rollback: Hebt eine Zuweisung auf, indem ein NEUES Rollback-Event
 * angehängt wird (Status zurück auf "open"). Historie bleibt vollständig –
 * es wird nichts gelöscht oder überschrieben.
 */
class ShiftRollbackService
{
    public function __construct(
        private readonly ShiftAuditLedger $ledger,
    ) {}

    public function rollback(Shift $shift, string $reason, ?string $cancellationMessage = null): ShiftAuditEvent
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Ein Rückrollgrund ist erforderlich (Revisionssicherheit).');
        }

        return DB::transaction(function () use ($shift, $reason, $cancellationMessage): ShiftAuditEvent {
            $shift = Shift::whereKey($shift->id)->lockForUpdate()->firstOrFail();

            if ($shift->status !== ShiftStatus::Assigned || $shift->assigned_employee_id === null) {
                throw new InvalidArgumentException('Nur besetzte Schichten können zurückgerollt werden.');
            }

            // Referenz auf die rückgängig gemachte Zuweisung (jüngstes Assign-Event).
            $revertedEventId = ShiftAuditEvent::where('shift_id', $shift->id)
                ->whereIn('event_type', [AuditEventType::InitialAssignment, AuditEventType::ManualOverride])
                ->latest('version')
                ->value('id');

            $previousState = $shift->snapshot();
            $shift->update([
                'assigned_employee_id' => null,
                'status' => ShiftStatus::Open,
            ]);

            $newState = $shift->fresh()->snapshot();
            $newState['rollback_reason'] = $reason;

            if ($cancellationMessage !== null && $cancellationMessage !== '') {
                $newState['cancellation_message'] = $cancellationMessage;
            }

            return $this->ledger->record($shift, AuditEventType::Rollback, $previousState, $newState, $revertedEventId);
        });
    }
}
