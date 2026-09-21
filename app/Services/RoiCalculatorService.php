<?php

namespace App\Services;

/**
 * Reine ROI-Arithmetik ohne Framework-Abhängigkeiten (unit-testbar):
 * Eingesparte Disponenten-Kosten, Automatisierungsquote, Match-Konfidenz.
 */
final class RoiCalculatorService
{
    /**
     * Manuelle Dispositionszeit pro gelöstem Konflikt (Minuten).
     */
    public const DISPATCHER_MINUTES_SAVED_PER_CONFLICT = 45;

    /**
     * Disponenten-Stundensatz in EUR.
     */
    public const DISPATCHER_HOURLY_RATE_EUR = 65.0;

    /**
     * Eingesparte Kosten: Konflikte × 45 Min × 65 €/h.
     */
    public function savedCostsEur(int $resolvedConflicts): float
    {
        return round(max(0, $resolvedConflicts) * (self::DISPATCHER_MINUTES_SAVED_PER_CONFLICT / 60) * self::DISPATCHER_HOURLY_RATE_EUR, 2);
    }

    /**
     * Anteil der ohne Nacharbeit übernommenen Matches in %. Null bei leerer Basis.
     */
    public function automationRate(int $adoptedWithoutRework, int $totalMatches): ?float
    {
        if ($totalMatches <= 0) {
            return null;
        }

        return round(max(0, $adoptedWithoutRework) / $totalMatches * 100, 1);
    }

    /**
     * Durchschnittliche Match-Konfidenz (0-100). Null bei leerer Basis.
     *
     * @param  iterable<int>  $scores
     */
    public function averageConfidence(iterable $scores): ?float
    {
        $scores = is_array($scores) ? $scores : iterator_to_array($scores);

        if ($scores === []) {
            return null;
        }

        return round(array_sum($scores) / count($scores), 1);
    }
}
