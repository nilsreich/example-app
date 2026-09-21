<?php

namespace App\Filament\Widgets;

use App\Services\RoiMetricsService;
use Filament\Widgets\ChartWidget;

class FeedbackChartWidget extends ChartWidget
{
    protected ?string $heading = 'Feedback zu KI-Vorschlägen';

    protected function getData(): array
    {
        $distribution = app(RoiMetricsService::class)->feedbackDistribution();

        return [
            'labels' => $distribution['labels'],
            'datasets' => [
                [
                    'data' => $distribution['data'],
                    'backgroundColor' => ['#10b981', '#ef4444'],
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
