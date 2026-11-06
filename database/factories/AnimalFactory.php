<?php

namespace Database\Factories;

use App\Models\Animal;
use App\Models\Breed;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Animal>
 */
class AnimalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ear_tag' => strtoupper(fake()->unique()->bothify('HT-####')),
            'name' => fake()->optional()->firstName(),
            'species' => 'bovine',
            'breed_id' => Breed::factory(),
            'sex' => fake()->randomElement(['female', 'male', 'unknown']),
            'identification_method' => 'visual',
            'birth_date' => fake()->dateTimeBetween('-8 years', 'now')->format('Y-m-d'),
            'purpose' => 'dual',
            'status' => 'active',
            'acquisition_type' => 'born',
        ];
    }
}
