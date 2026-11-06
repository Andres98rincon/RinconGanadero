<?php

namespace Database\Factories;

use App\Models\Animal;
use App\Models\ReproductiveEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReproductiveEvent>
 */
class ReproductiveEventFactory extends Factory
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
            'event_type' => 'heat',
            'event_date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
        ];
    }
}
