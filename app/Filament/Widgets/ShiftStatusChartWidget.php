<?php

namespace App\Filament\Widgets;

use App\Services\RoiMetricsService;
use Filament\Widgets\ChartWidget;

class ShiftStatusChartWidget extends ChartWidget
{
    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->role->seesMetrics();
    }

    protected ?string $heading = 'Schichtstatus-Verteilung';

    protected function getData(): array
    {
        $distribution = app(RoiMetricsService::class)->forUser(auth()->user())->shiftStatusDistribution();

        return [
            'labels' => $distribution['labels'],
            'datasets' => [
                [
                    'data' => $distribution['data'],
                    // Rot = offen (Handlungsbedarf), Grün = besetzt, Grau = storniert.
                    'backgroundColor' => ['#ef4444', '#10b981', '#9ca3af'],
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
