<?php

namespace Database\Factories;

use App\Models\Animal;
use App\Models\AnimalMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnimalMovement>
 */
class AnimalMovementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'movement_type' => 'paddock_transfer',
            'movement_date' => today()->toDateString(),
            'animal_id' => Animal::factory(),
            'animal_ear_tag' => fn (array $attributes): string => Animal::query()->find($attributes['animal_id'])->ear_tag,
        ];
    }
}
