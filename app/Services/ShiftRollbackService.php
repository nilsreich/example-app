<?php

namespace App\Services;

use App\Audit\AuditLedger;
use App\Audit\Enums\AuditEventType;
use App\Audit\Models\AuditEvent;
use App\Enums\ShiftStatus;
use App\Models\Shift;
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
        private readonly AuditLedger $ledger,
    ) {}

    public function rollback(Shift $shift, string $reason, ?string $cancellationMessage = null): AuditEvent
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Ein Rückrollgrund ist erforderlich (Revisionssicherheit).');
        }

        return DB::transaction(function () use ($shift, $reason, $cancellationMessage): AuditEvent {
            $shift = Shift::whereKey($shift->id)->lockForUpdate()->firstOrFail();

            if ($shift->status !== ShiftStatus::Assigned || $shift->assigned_employee_id === null) {
                throw new InvalidArgumentException('Nur besetzte Schichten können zurückgerollt werden.');
            }

            // Referenz auf die rückgängig gemachte Zuweisung (jüngstes Assign-Event).
            $revertedEventId = AuditEvent::where('auditable_type', Shift::class)
                ->where('auditable_id', $shift->id)
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
            $newState['reverted_event_id'] = $revertedEventId;

            if ($cancellationMessage !== null && $cancellationMessage !== '') {
                $newState['cancellation_message'] = $cancellationMessage;
            }

            return $this->ledger->record(
                eventType: AuditEventType::Rollback,
                previousState: $previousState,
                newState: $newState,
                auditable: $shift,
            );
        });
    }
}
