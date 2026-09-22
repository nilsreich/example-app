<?php

namespace Tests\Feature;

use App\Filament\Widgets\FeedbackChartWidget;
use App\Filament\Widgets\RoiStatsWidget;
use App\Filament\Widgets\SavingsChartWidget;
use App\Filament\Widgets\ShiftStatusChartWidget;
use App\Models\Employee;
use App\Models\Shift;
use App\Services\ShiftAssignmentService;
use App\Services\ShiftOptimizationRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FilamentWidgetsTest extends TestCase
{
    use RefreshDatabase;

    private function seedOneResolvedConflict(): void
    {
        $shift = Shift::factory()->create(['required_qualifications' => []]);
        Employee::factory()->create();

        $runner = app(ShiftOptimizationRunner::class);
        $optimization = $runner->run($shift);

        app(ShiftAssignmentService::class)->assign($shift, $optimization->proposals->first()->employee);
    }

    public function test_roi_stats_widget_renders_kpis_from_database(): void
    {
        $this->seedOneResolvedConflict();

        Livewire::test(RoiStatsWidget::class)
            ->assertSee('Eingesparte Disponentenkosten')
            // 1 Konflikt × 45 Min × 65 €/h = 48,75 €.
            ->assertSee('48,75 €')
            ->assertSee('Automatisierungsquote')
            ->assertSee('100,0 %')
            ->assertSee('Ø Match-Konfidenz');
    }

    public function test_chart_widgets_render(): void
    {
        $this->seedOneResolvedConflict();

        Livewire::test(SavingsChartWidget::class)->assertSee('Kosten- & Zeiteinsparung');
        Livewire::test(ShiftStatusChartWidget::class)->assertSee('Schichtstatus-Verteilung');
        Livewire::test(FeedbackChartWidget::class)->assertSee('Feedback zu KI-Vorschlägen');
    }

    public function test_widgets_render_on_empty_database(): void
    {
        Livewire::test(RoiStatsWidget::class)->assertSee('Eingesparte Disponentenkosten');
        Livewire::test(SavingsChartWidget::class)->assertSee('Kosten- & Zeiteinsparung');
    }
}
