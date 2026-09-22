<?php

namespace Tests\Feature;

use App\Ai\Agents\ShiftOptimizerAgent;
use App\Ai\Services\AgentRegistry;
use App\Enums\PipelineDriver;
use App\Models\Employee;
use App\Models\Setting;
use App\Models\Shift;
use App\Services\ShiftOptimizationRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_registry_resolves_shift_optimizer_agent_class(): void
    {
        $this->assertInstanceOf(ShiftOptimizerAgent::class, app(AgentRegistry::class)->agent('shift-optimizer'));
    }

    public function test_mock_runner_is_deterministic(): void
    {
        $shift = Shift::factory()->create(['required_qualifications' => ['Staplerschein']]);
        Employee::factory()->create(['qualifications' => ['Staplerschein']]);

        $first = app(ShiftOptimizationRunner::class)->run($shift);
        $second = app(ShiftOptimizationRunner::class)->run($shift);

        $this->assertSame(PipelineDriver::Mock, $first->driver_used);
        $this->assertSame(
            $first->proposals->map(fn ($match) => [$match->employee_id, $match->score])->all(),
            $second->proposals->map(fn ($match) => [$match->employee_id, $match->score])->all(),
        );
        $this->assertNotEmpty($first->proposals);
    }

    public function test_mock_runner_prefers_fully_qualified_candidates(): void
    {
        $shift = Shift::factory()->create(['required_qualifications' => ['Staplerschein', 'Ersthelfer']]);
        $unqualified = Employee::factory()->create(['name' => 'Ohne Schein', 'qualifications' => []]);
        $qualified = Employee::factory()->create(['name' => 'Mit Schein', 'qualifications' => ['Staplerschein', 'Ersthelfer']]);

        $optimization = app(ShiftOptimizationRunner::class)->run($shift);
        $proposals = $optimization->proposals;

        $this->assertSame($qualified->id, $proposals->first()->employee_id);
        $this->assertGreaterThan($proposals->get(1)->score, $proposals->first()->score);
        $this->assertNotEmpty($proposals->first()->match_reasons);
        $this->assertNotEmpty($proposals->first()->draft_message);
        $this->assertContains($unqualified->id, $proposals->pluck('employee_id')->all());
    }

    public function test_live_runner_maps_structured_response_and_filters_unknown_ids(): void
    {
        Setting::set(Setting::AI_PIPELINE_MODE, PipelineDriver::Live->value);

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

        $optimization = app(ShiftOptimizationRunner::class)->run($shift);

        $this->assertSame(PipelineDriver::Live, $optimization->driver_used);
        $this->assertCount(1, $optimization->proposals);
        $this->assertSame($employee->id, $optimization->proposals->first()->employee_id);
        $this->assertSame(100, $optimization->proposals->first()->score);
        ShiftOptimizerAgent::assertPromptedTimes(1);
    }

    public function test_runner_persists_conversation_for_audit_trail(): void
    {
        $shift = Shift::factory()->create();
        Employee::factory()->create();

        $optimization = app(ShiftOptimizationRunner::class)->run($shift);

        $this->assertNotNull($optimization->raw_response['conversation_id'] ?? null);
        $this->assertDatabaseHas('agent_conversations', [
            'title' => 'shift-optimizer',
            'participant_type' => Shift::class,
            'participant_id' => $shift->id,
        ]);
    }
}
