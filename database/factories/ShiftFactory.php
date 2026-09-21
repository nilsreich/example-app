<?php

namespace Database\Factories;

use App\Enums\ShiftStatus;
use App\Models\Employee;
use App\Models\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    /**
     * Standard: offene Tagschicht mit Qualifikationsanforderung.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('+1 day', '+3 days')->setTime(6, 0);

        return [
            'title' => fake()->randomElement(['Frühschicht Logistik', 'Spätschicht Produktion', 'Nachtschicht Versand', 'Wochenenddienst Logistik']),
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->modify('+8 hours'),
            'department' => fake()->randomElement(['Logistik', 'Produktion', 'Versand']),
            'required_qualifications' => ['Staplerschein'],
            'status' => ShiftStatus::Open,
            'assigned_employee_id' => null,
        ];
    }

    /**
     * Besetzte Schicht mit zugewiesenem Mitarbeiter.
     */
    public function assigned(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ShiftStatus::Assigned,
            'assigned_employee_id' => Employee::factory(),
        ]);
    }

    /**
     * Stornierte Schicht.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ShiftStatus::Cancelled,
        ]);
    }
}
