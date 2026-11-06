<?php

namespace Database\Factories;

use App\Models\FinancialTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancialTransaction>
 */
class FinancialTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(array_keys(FinancialTransaction::TYPES));

        return [
            'type' => $type,
            'category' => fake()->randomElement(array_keys(FinancialTransaction::CATEGORIES[$type])),
            'title' => fake()->sentence(3),
            'amount' => fake()->randomFloat(2, 1, 100000),
            'transaction_date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'payment_method' => fake()->randomElement(array_keys(FinancialTransaction::PAYMENT_METHODS)),
        ];
    }
}
