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
     * Lädt den Kettenkontext einmalig (id → hash, aufsteigend, plus direkter
     * Vorgänger je id). Für Streaming-Exporte, die nicht alle Events im
     * Speicher halten wollen.
     *
     * @return array{chain: array<int, string>, predecessorOf: array<int, int|null>}
     */
    public static function context(): array
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

        return ['chain' => $chain, 'predecessorOf' => $predecessorOf];
    }

    /**
     * Bewertet einen einzelnen Block gegen den zuvor geladenen Kettenkontext.
     *
     * @param  array{chain: array<int, string>, predecessorOf: array<int, int|null>}  $context
     */
    public static function evaluate(AuditEvent $event, array $context): bool
    {
        $predecessorId = $context['predecessorOf'][$event->id] ?? null;

        $expectedPrevHash = $predecessorId === null
            ? null
            : ($context['chain'][$predecessorId] ?? null);

        return $event->isChainValid() && $event->prev_hash === $expectedPrevHash;
    }

    /**
     * Bewertet jeden Block der gegebenen (ggf. gefilterten) Export-Menge
     * gegen die vollständige, ungefilterte Ledger-Kette.
     *
     * @param  Collection<int, AuditEvent>  $events
     * @return array<int, bool> Event-ID → ketten-gültig ja/nein
     */
    public static function verifyAll(Collection $events): array
    {
        $context = self::context();

        $states = [];

        foreach ($events as $event) {
            $states[$event->id] = self::evaluate($event, $context);
        }

        return $states;
    }
}
