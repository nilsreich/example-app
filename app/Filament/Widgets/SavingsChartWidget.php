<?php

namespace App\Filament\Widgets;

use App\Services\RoiMetricsService;
use Filament\Widgets\ChartWidget;

class SavingsChartWidget extends ChartWidget
{
    protected static bool $isLazy = false;

    protected ?string $heading = 'Kosten- & Zeiteinsparung (14 Tage)';

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $savings = app(RoiMetricsService::class)->savingsPerDay();

        return [
            'labels' => $savings['labels'],
            'datasets' => [
                [
                    'label' => 'Ersparnis (€)',
                    'data' => $savings['costs'],
                    'backgroundColor' => '#10b981',
                    'borderColor' => '#059669',
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
