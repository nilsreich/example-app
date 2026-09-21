<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\ShiftOptimization;
use App\Models\ShiftProposal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShiftProposal>
 */
class ShiftProposalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'optimization_id' => ShiftOptimization::factory(),
            'employee_id' => Employee::factory(),
            'score' => fake()->numberBetween(70, 98),
            'match_reasons' => ['Verfügt über alle geforderten Qualifikationen.', 'Ruhezeit seit letzter Schicht eingehalten.'],
            'risks_or_tradeoffs' => ['Erhöht das Wochenüberstundenkonto.'],
            'draft_message' => 'Hallo, kannst du kurzfristig einspringen? Rückmeldung bitte bis 18 Uhr.',
        ];
    }
}
