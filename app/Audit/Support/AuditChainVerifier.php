<?php

namespace App\Audit\Support;

use App\Audit\Models\AuditEvent;
use Illuminate\Database\Eloquent\Collection;

/**
 * Prüft die Kontinuität der globalen Audit-Hash-Kette (GoBD).
 *
 * Ein Block ist nur dann ketten-gültig, wenn
 *   1. sein eigener Hash zu prev_hash + blockPayload passt (Recompute) und
 *   2. sein prev_hash im Ledger als Hash eines tatsächlichen Vorgänger-Blocks
 *      existiert (Linkage). Modifiziert ein Angreifer einen Mittel-Block
 *      samt neuem Hash, bricht die Verkettung zum Folgeblock ausschließlich
 *      hierdurch sichtbar auf.
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
        $chainHashes = AuditEvent::query()
            ->whereNotNull('hash')
            ->pluck('hash')
            ->mapWithKeys(static fn (string $hash): array => [$hash => true])
            ->all();

        $states = [];

        foreach ($events as $event) {
            $linksForward = $event->prev_hash === null
                || isset($chainHashes[$event->prev_hash]);

            $states[$event->id] = $event->isChainValid() && $linksForward;
        }

        return $states;
    }
}
