<?php

namespace App\Audit;

use App\Audit\Enums\AuditEventType;
use App\Audit\Enums\AuditSource;
use App\Audit\Models\AuditEvent;
use App\Audit\Support\HashChain;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Einzige Schreibstelle für das Audit-Ledger.
 *
 * - Append-only: Events werden nur eingefügt (Modell-Guard verhindert Update/Delete).
 * - Globale Hash-Kette: prev_hash jedes Events ist der Hash des letzten Events insgesamt.
 * - Versionen pro Entität (auditable_type + auditable_id); System-Events ohne
 *   auditable zählen in eigener Bucket.
 */
final class AuditLedger
{
    /**
     * @param  array<string, mixed>  $previousState
     * @param  array<string, mixed>  $newState
     */
    public function record(
        AuditEventType $eventType,
        array $previousState,
        array $newState,
        ?Model $auditable = null,
        ?User $actor = null,
        AuditSource $source = AuditSource::Web,
        ?string $ip = null,
        ?string $userAgent = null,
    ): AuditEvent {
        return DB::transaction(function () use ($eventType, $previousState, $newState, $auditable, $actor, $source, $ip, $userAgent): AuditEvent {
            $auditableType = $auditable?->getMorphClass();
            $auditableId = $auditable?->getKey();

            $version = (int) $this->entityVersionQuery($auditableType, $auditableId)->max('version') + 1;

            $prevHash = AuditEvent::query()
                ->orderByDesc('id')
                ->lockForUpdate()
                ->value('hash');

            $event = new AuditEvent;
            $event->auditable_type = $auditableType;
            $event->auditable_id = $auditableId;
            $event->actor_user_id = $actor?->getKey();
            $event->event_type = $eventType;
            $event->previous_state = $previousState;
            $event->new_state = $newState;
            $event->version = $version;
            $event->source = $source;
            $event->ip = $ip;
            $event->user_agent = $userAgent;
            $event->prev_hash = $prevHash;
            $event->hash = HashChain::hash($prevHash, $event->blockPayload());
            $event->save();

            return $event;
        });
    }

    /**
     * Versions-Bucket: maximale bisherige Version für genau diese Entität
     * (bzw. für System-Events ohne auditable).
     *
     * @return Builder<AuditEvent>
     */
    private function entityVersionQuery(?string $auditableType, int|string|null $auditableId): Builder
    {
        $query = AuditEvent::query()->lockForUpdate();

        if ($auditableType === null) {
            return $query->whereNull('auditable_type')->whereNull('auditable_id');
        }

        return $query
            ->where('auditable_type', $auditableType)
            ->where('auditable_id', $auditableId);
    }
}
