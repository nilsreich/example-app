<?php

namespace App\Filament\Widgets;

use App\Services\RoiMetricsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RoiStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $metrics = app(RoiMetricsService::class);

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
