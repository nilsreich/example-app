<?php

namespace App\Filament\Widgets;

use App\Services\RoiMetricsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RoiStatsWidget extends StatsOverviewWidget
{
    // Non-lazy: KPIs werden serverseitig gerendert (kein Livewire-Nachladen nötig).
    protected static bool $isLazy = false;

    /**
     * Nutzer (Self-Service) sehen keine ROI-Kennzahlen – nur ihre Schichten.
     */
    public static function canView(): bool
    {
        return (bool) auth()->user()?->role->seesMetrics();
    }

    protected function getStats(): array
    {
        // Abteilungs-Scope der Rolle beachten (Bereichsleiter → eigene Abteilung).
        $metrics = app(RoiMetricsService::class)->forUser(auth()->user());

        $automationRate = $metrics->automationRate();
        $averageConfidence = $metrics->averageConfidence();

        return [
            Stat::make(
                'Eingesparte Disponentenkosten',
                number_format($metrics->savedCostsEur(), 2, ',', '.').' €',
            )
                ->description($metrics->resolvedConflicts().' gelöste Konflikte × 45 Min × 65 €/h')
                ->color('success'),
            Stat::make(
                'Automatisierungsquote',
                $automationRate === null ? '–' : number_format($automationRate, 1, ',', '.').' %',
            )
                ->description('Matches ohne Nacharbeit übernommen')
                ->color($automationRate !== null && $automationRate >= 70 ? 'success' : 'warning'),
            Stat::make(
                'Ø Match-Konfidenz',
                $averageConfidence === null ? '–' : number_format($averageConfidence, 1, ',', '.').' %',
            )
                ->description('Durchschnitt aller KI-Scores')
                ->color('info'),
        ];
    }
}
