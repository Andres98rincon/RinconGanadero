<?php

namespace Database\Factories;

use App\Models\AnimalValuation;
use App\Models\Animal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnimalValuation>
 */
class AnimalValuationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'animal_id' => Animal::factory(),
            'amount' => fake()->randomFloat(2, 100, 1000000),
            'valuation_date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
