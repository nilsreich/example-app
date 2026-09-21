<?php

namespace Tests\Feature;

use App\Ai\Agents\ShiftOptimizerAgent;
use App\Contracts\ShiftOptimizerPipelineInterface;
use App\Data\OptimizationResult;
use App\Enums\PipelineDriver;
use App\Models\Employee;
use App\Models\Setting;
use App\Models\Shift;
use App\Pipelines\LaravelAiSdkPipeline;
use App\Pipelines\MockDeterministicPipeline;
use App\Services\ShiftCandidateContextBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftPipelineTest extends TestCase
{
    use RefreshDatabase;

    private function mockPipeline(): MockDeterministicPipeline
    {
        // Latenz 0 für schnelle Tests; Produktiv-Default bleibt 800 ms.
        return new MockDeterministicPipeline(new ShiftCandidateContextBuilder, 0);
    }

    public function test_container_resolves_mock_pipeline_by_default(): void
    {
        $this->assertInstanceOf(MockDeterministicPipeline::class, app(ShiftOptimizerPipelineInterface::class));
    }

    public function test_container_resolves_live_pipeline_when_configured(): void
    {
        Setting::set(Setting::AI_PIPELINE_MODE, PipelineDriver::Live->value);

        $this->assertInstanceOf(LaravelAiSdkPipeline::class, app(ShiftOptimizerPipelineInterface::class));
    }

    public function test_unknown_pipeline_mode_falls_back_to_mock(): void
    {
        Setting::set(Setting::AI_PIPELINE_MODE, 'skynet');

        $this->assertInstanceOf(MockDeterministicPipeline::class, app(ShiftOptimizerPipelineInterface::class));
    }

    public function test_mock_pipeline_is_deterministic(): void
    {
        $shift = Shift::factory()->create(['required_qualifications' => ['Staplerschein']]);
        Employee::factory()->create(['qualifications' => ['Staplerschein']]);

        $first = $this->mockPipeline()->optimize($shift);
        $second = $this->mockPipeline()->optimize($shift);

        $this->assertInstanceOf(OptimizationResult::class, $first);
        $this->assertSame(PipelineDriver::Mock, $first->driver);
        $this->assertSame(
            array_map(fn ($match) => $match->toArray(), $first->matches),
            array_map(fn ($match) => $match->toArray(), $second->matches),
        );
    }

    public function test_mock_pipeline_prefers_fully_qualified_candidates(): void
    {
        $shift = Shift::factory()->create(['required_qualifications' => ['Staplerschein', 'Ersthelfer']]);
        $unqualified = Employee::factory()->create(['name' => 'Ohne Schein', 'qualifications' => []]);
        $qualified = Employee::factory()->create(['name' => 'Mit Schein', 'qualifications' => ['Staplerschein', 'Ersthelfer']]);

        $result = $this->mockPipeline()->optimize($shift);

        $this->assertSame($qualified->id, $result->matches[0]->employeeId);
        $this->assertGreaterThan($result->matches[1]->score, $result->matches[0]->score);
        $this->assertNotEmpty($result->matches[0]->matchReasons);
        $this->assertNotEmpty($result->matches[0]->draftMessage);
        $this->assertContains($unqualified->id, array_map(fn ($match) => $match->employeeId, $result->matches));
    }

    public function test_live_pipeline_maps_structured_response_and_filters_unknown_ids(): void
    {
        $shift = Shift::factory()->create();
        $employee = Employee::factory()->create();

        ShiftOptimizerAgent::fake([[
            'matches' => [
                [
                    'employee_id' => $employee->id,
                    'score' => 150, // Außerhalb 0-100 → wird auf 100 geklemmt.
                    'match_reasons' => ['Passt perfekt.'],
                    'risks_or_tradeoffs' => [],
                    'draft_message' => 'Hallo, springst du ein?',
                ],
                ['employee_id' => 999_999, 'score' => 99], // Phantom-ID → wird verworfen.
            ],
        ]]);

        $result = app(LaravelAiSdkPipeline::class)->optimize($shift);

        $this->assertSame(PipelineDriver::Live, $result->driver);
        $this->assertCount(1, $result->matches);
        $this->assertSame($employee->id, $result->matches[0]->employeeId);
        $this->assertSame(100, $result->matches[0]->score);
        ShiftOptimizerAgent::assertPromptedTimes(1);
    }
}
