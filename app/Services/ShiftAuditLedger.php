<?php

namespace App\Services;

use App\Enums\AuditEventType;
use App\Models\Shift;
use App\Models\ShiftAuditEvent;
use Illuminate\Support\Facades\DB;

/**
 * Einzige Schreibstelle für den Forward-Ledger: Vergibt die Versionsnummer
 * transaktional (nächste freie Version je Schicht) und hängt Events an.
 */
class ShiftAuditLedger
{
    /**
     * @param  array<string, mixed>  $previousState
     * @param  array<string, mixed>  $newState
     */
    public function record(
        Shift $shift,
        AuditEventType $eventType,
        array $previousState,
        array $newState,
        ?int $revertedEventId = null,
    ): ShiftAuditEvent {
        return DB::transaction(function () use ($shift, $eventType, $previousState, $newState, $revertedEventId): ShiftAuditEvent {
            $nextVersion = (int) (ShiftAuditEvent::where('shift_id', $shift->id)->lockForUpdate()->max('version') ?? 0) + 1;

            return ShiftAuditEvent::create([
                'shift_id' => $shift->id,
                'version' => $nextVersion,
                'event_type' => $eventType,
                'previous_state' => $previousState,
                'new_state' => $newState,
                'reverted_event_id' => $revertedEventId,
            ]);
        });
    }
}
