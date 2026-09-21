<?php

namespace Database\Factories;

use App\Enums\PipelineDriver;
use App\Models\Shift;
use App\Models\ShiftOptimization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShiftOptimization>
 */
class ShiftOptimizationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shift_id' => Shift::factory(),
            'driver_used' => PipelineDriver::Mock,
            'prompt_payload' => ['shift_id' => null, 'required_qualifications' => []],
            'raw_response' => ['driver' => PipelineDriver::Mock->value, 'proposals' => []],
            'execution_time_ms' => fake()->numberBetween(700, 900),
            'tokens_used' => null,
            'cost_estimate_eur' => '0.000000',
        ];
    }

    /**
     * Live-Lauf mit Token-Verbrauch und Inferenzkosten.
     */
    public function live(): static
    {
        return $this->state(fn (array $attributes) => [
            'driver_used' => PipelineDriver::Live,
            'tokens_used' => fake()->numberBetween(800, 2500),
            'cost_estimate_eur' => fake()->randomFloat(6, 0.001, 0.02),
        ]);
    }
}
