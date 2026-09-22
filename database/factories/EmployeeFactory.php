<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * Realistische kiventro-Demo-Belegschaft (deutsche Namen/Rollen/Abteilungen).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $qualifications = ['Staplerschein', 'Ersthelfer', 'Kranführerschein', 'ADR-Schein', 'Schichtleitung'];

        return [
            'name' => fake()->name(),
            'role' => fake()->randomElement(['Kommissionierer', 'Staplerfahrer', 'Schichtleiter', 'Versandmitarbeiter', 'Produktionshelfer']),
            'department' => fake()->randomElement(['Logistik', 'Produktion', 'Versand']),
            'qualifications' => fake()->randomElements($qualifications, fake()->numberBetween(0, 3)),
            'weekly_overtime_minutes' => fake()->numberBetween(0, 600),
            'last_shift_ended_at' => fake()->optional()->dateTimeBetween('-2 days', 'now'),
            'is_active' => true,
        ];
    }

    /**
     * Krank/beurlaubt: fällt aus der Kandidatenauswahl (is_active = false).
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Erfüllt die Standard-Pflichtqualifikation der ShiftFactory (Staplerschein).
     */
    public function qualified(): static
    {
        return $this->state(fn (array $attributes) => [
            'qualifications' => ['Staplerschein'],
        ]);
    }
}
