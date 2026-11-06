<?php

namespace Database\Factories;

use App\Models\Animal;
use App\Models\MilkProductionRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MilkProductionRecord>
 */
class MilkProductionRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'animal_id' => Animal::factory()->state(['sex' => 'female']),
            'record_date' => fake()->unique()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'morning_liters' => fake()->randomFloat(2, 0, 20),
            'evening_liters' => fake()->randomFloat(2, 0, 20),
        ];
    }
}
