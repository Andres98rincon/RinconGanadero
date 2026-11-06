<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\Farm;
use App\Models\FinancialTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialTransactionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_an_animal_expense_assigns_the_animals_farm(): void
    {
        $this->freezeTime();
        $farm = Farm::factory()->create();
        $animal = Animal::factory()->create(['farm_id' => $farm->id]);

        $response = $this->post(route('finance.transactions.store'), [
            'type' => 'expense',
            'category' => 'feed',
            'title' => 'Concentrado del mes',
            'amount' => 325.75,
            'transaction_date' => today()->toDateString(),
            'animal_id' => $animal->id,
            'payment_method' => 'bank_transfer',
            'reference' => 'TRX-001',
            'notes' => 'Compra de alimento',
            'is_reconciled' => true,
        ]);

        $transaction = FinancialTransaction::query()->firstOrFail();
        $response->assertRedirect(route('finance.index', [
            'from' => today()->toDateString(),
            'to' => today()->toDateString(),
            'farm_id' => $farm->id,
            'animal_id' => $animal->id,
        ]));
        $this->assertDatabaseHas('financial_transactions', [
            'id' => $transaction->id,
            'type' => 'expense',
            'category' => 'feed',
            'amount' => 325.75,
            'animal_id' => $animal->id,
            'farm_id' => $farm->id,
            'reference' => 'TRX-001',
        ]);
        $this->assertDatabaseMissing('financial_transactions', [
            'id' => $transaction->id,
            'is_reconciled' => true,
        ]);
    }

    public function test_rejects_an_expense_linked_to_a_different_farm(): void
    {
        $animalFarm = Farm::factory()->create();
        $otherFarm = Farm::factory()->create();
        $animal = Animal::factory()->create(['farm_id' => $animalFarm->id]);

        $response = $this->post(route('finance.transactions.store'), [
            'type' => 'expense',
            'category' => 'feed',
            'title' => 'Alimento',
            'amount' => 50,
            'transaction_date' => today()->subDay()->toDateString(),
            'farm_id' => $otherFarm->id,
            'animal_id' => $animal->id,
        ]);

        $response->assertSessionHasErrors([
            'farm_id' => 'La finca seleccionada no corresponde al animal.',
        ]);
        $this->assertDatabaseCount('financial_transactions', 0);
    }

    public function test_rejects_a_category_that_does_not_match_the_transaction_type(): void
    {
        $response = $this->post(route('finance.transactions.store'), [
            'type' => 'income',
            'category' => 'feed',
            'title' => 'Movimiento inválido',
            'amount' => 50,
            'transaction_date' => today()->subDay()->toDateString(),
        ]);

        $response->assertSessionHasErrors([
            'category' => 'La categoría no corresponde al tipo de movimiento.',
        ]);
        $this->assertDatabaseCount('financial_transactions', 0);
    }

    public function test_animal_purchase_and_sale_categories_can_only_be_registered_through_the_movement_module(): void
    {
        foreach ([
            ['type' => 'expense', 'category' => 'animal_purchase'],
            ['type' => 'income', 'category' => 'animal_sales'],
        ] as $transaction) {
            $response = $this->post(route('finance.transactions.store'), [
                ...$transaction,
                'title' => 'Movimiento manual duplicado',
                'amount' => 100,
                'transaction_date' => today()->toDateString(),
            ]);

            $response->assertSessionHasErrors([
                'category' => 'Las compras y ventas de animales se registran desde el módulo de movimientos.',
            ]);
        }

        $this->assertDatabaseCount('financial_transactions', 0);
    }

    public function test_requires_transaction_type_description_amount_and_date(): void
    {
        $response = $this->post(route('finance.transactions.store'), []);

        $response->assertSessionHasErrors(['type', 'category', 'title', 'amount', 'transaction_date']);
        $this->assertDatabaseCount('financial_transactions', 0);
    }
}
