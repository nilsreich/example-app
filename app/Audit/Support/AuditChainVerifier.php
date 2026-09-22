<?php

namespace App\Audit\Support;

use App\Audit\Models\AuditEvent;
use Illuminate\Database\Eloquent\Collection;

/**
 * Prüft die Kontinuität der globalen Audit-Hash-Kette (GoBD).
 *
 * Ein Block ist nur dann ketten-gültig, wenn
 *   1. sein eigener Hash zu prev_hash + blockPayload passt (Recompute),
 *   2. sein prev_hash exakt der Hash des unmittelbaren Vorgängers (id-1) ist –
 *      bzw. null für den Kettenkopf. Diese strenge Linearität erkennt auch
 *      Re-Parenting, bei dem ein Mittel-Block samt Hash konsistent neu berechnet
 *      wurde: die Nachbarschaft stimmt dann nicht mehr.
 *
 * Ohne externen Anker/HMAC liefert die Kette Evidenz gegen Teiländerungen, nicht
 * gegen einen Angreifer mit vollem DB-Schreibzugriff (der die gesamte Kette neu
 * berechnen könnte).
 */
final class AuditChainVerifier
{
    /**
     * Bewertet jeden Block der gegebenen (ggf. gefilterten) Export-Menge
     * gegen die vollständige, ungefilterte Ledger-Kette.
     *
     * @param  Collection<int, AuditEvent>  $events
     * @return array<int, bool> Event-ID → ketten-gültig ja/nein
     */
    public static function verifyAll(Collection $events): array
    {
        /** @var array<int, string> $chain id → hash, aufsteigend */
        $chain = AuditEvent::query()->orderBy('id')->pluck('hash', 'id')->all();

        // Für jede id den direkten Vorgänger (gemäß Reihenfolge, nicht id−1-Arithmetik).
        $predecessorOf = [];
        $previousId = null;

        foreach (array_keys($chain) as $id) {
            $predecessorOf[$id] = $previousId;
            $previousId = $id;
        }

        $states = [];

        foreach ($events as $event) {
            $predecessorId = $predecessorOf[$event->id] ?? null;

            $expectedPrevHash = $predecessorId === null
                ? null
                : ($chain[$predecessorId] ?? null);

            $linksForward = $event->prev_hash === $expectedPrevHash;

            $states[$event->id] = $event->isChainValid() && $linksForward;
        }

        return $states;
    }
}
