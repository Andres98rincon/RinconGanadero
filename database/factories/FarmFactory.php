<?php

namespace Database\Factories;

use App\Models\Farm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Farm>
 */
class FarmFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('FIN-####')),
            'name' => fake()->company(),
            'address' => fake()->streetAddress(),
            'municipality' => fake()->city(),
            'province' => fake()->state(),
            'area_hectares' => fake()->randomFloat(2, 1, 1000),
            'manager_name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->safeEmail(),
            'status' => 'active',
        ];
    }
}
