<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Shift;
use App\Pipelines\MockDeterministicPipeline;
use App\Services\RoiCalculatorService;
use App\Services\RoiMetricsService;
use App\Services\ShiftAssignmentService;
use App\Services\ShiftCandidateContextBuilder;
use App\Services\ShiftOptimizationRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoiMetricsTest extends TestCase
{
    use RefreshDatabase;

    private function metrics(): RoiMetricsService
    {
        return new RoiMetricsService(new RoiCalculatorService);
    }

    private function fastRunner(): ShiftOptimizationRunner
    {
        return new ShiftOptimizationRunner(new MockDeterministicPipeline(new ShiftCandidateContextBuilder, 0));
    }

    public function test_empty_database_yields_zero_and_nulls(): void
    {
        $metrics = $this->metrics();

        $this->assertSame(0, $metrics->resolvedConflicts());
        $this->assertSame(0.0, $metrics->savedCostsEur());
        $this->assertNull($metrics->automationRate());
        $this->assertNull($metrics->averageConfidence());
        $this->assertSame(['Offen', 'Besetzt', 'Storniert'], $metrics->shiftStatusDistribution()['labels']);
    }

    public function test_resolved_conflicts_and_automation_rate(): void
    {
        // Schicht A: Top-Match wird übernommen → zählt als automatisiert.
        $shiftA = Shift::factory()->create(['required_qualifications' => []]);
        Employee::factory()->create();
        $optimization = $this->fastRunner()->run($shiftA);
        $top = $optimization->proposals->first();
        app(ShiftAssignmentService::class)->assign($shiftA, $top->employee);

        // Schicht B: manuell besetzt ohne Pipeline-Lauf → gelöst, aber keine Automations-Basis.
        app(ShiftAssignmentService::class)->assign(Shift::factory()->create(), Employee::factory()->create());

        $metrics = $this->metrics();

        $this->assertSame(2, $metrics->resolvedConflicts());
        $this->assertSame(97.5, $metrics->savedCostsEur());
        $this->assertSame(100.0, $metrics->automationRate());
        $this->assertEqualsWithDelta($top->score, $metrics->averageConfidence(), 0.05);
    }

    public function test_savings_per_day_covers_last_14_days(): void
    {
        $savings = $this->metrics()->savingsPerDay();

        $this->assertCount(14, $savings['labels']);
        $this->assertCount(14, $savings['costs']);
        $this->assertSame(0.0, array_sum($savings['costs']));
    }
}
