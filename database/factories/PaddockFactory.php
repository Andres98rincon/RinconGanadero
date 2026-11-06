<?php

namespace Database\Factories;

use App\Models\Farm;
use App\Models\Paddock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Paddock>
 */
class PaddockFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'code' => strtoupper(fake()->unique()->bothify('POT-####')),
            'name' => fake()->words(2, true),
            'area_hectares' => fake()->randomFloat(2, 0.1, 200),
            'capacity' => fake()->numberBetween(10, 300),
            'status' => 'active',
        ];
    }
}
