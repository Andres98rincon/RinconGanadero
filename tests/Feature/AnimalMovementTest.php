<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\AnimalMovement;
use App\Models\Farm;
use App\Models\FinancialTransaction;
use App\Models\Paddock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnimalMovementTest extends TestCase
{
    use RefreshDatabase;

    public function test_animal_purchase_creates_inventory_history_and_financial_expense(): void
    {
        $farm = Farm::factory()->create(['name' => 'Finca La Esperanza']);
        $paddock = Paddock::factory()->create(['farm_id' => $farm->id, 'name' => 'Potrero Norte']);

        $response = $this->post(route('animal-movements.store'), [
            'movement_type' => 'purchase',
            'movement_date' => today()->toDateString(),
            'ear_tag' => 'COMP-101',
            'name' => 'Lucera',
            'species' => 'bovine',
            'sex' => 'female',
            'to_farm_id' => $farm->id,
            'to_paddock_id' => $paddock->id,
            'amount' => 1800,
            'counterparty_name' => 'Ganadería El Valle',
            'payment_method' => 'bank_transfer',
            'reference' => 'FAC-101',
        ]);

        $animal = Animal::query()->where('ear_tag', 'COMP-101')->firstOrFail();
        $movement = AnimalMovement::query()->firstOrFail();
        $financialTransaction = FinancialTransaction::query()->firstOrFail();

        $response->assertRedirect(route('animal-movements.index', [
            'from' => today()->toDateString(),
            'to' => today()->toDateString(),
        ]));
        $this->assertDatabaseHas('animals', [
            'id' => $animal->id,
            'farm_id' => $farm->id,
            'paddock_id' => $paddock->id,
            'acquisition_type' => 'purchased',
            'acquisition_cost' => 1800,
            'supplier_name' => 'Ganadería El Valle',
        ]);
        $this->assertDatabaseHas('animal_movements', [
            'id' => $movement->id,
            'movement_type' => 'purchase',
            'animal_id' => $animal->id,
            'animal_ear_tag' => 'COMP-101',
            'to_farm_name' => 'Finca La Esperanza',
            'to_location' => 'Potrero Norte',
            'amount' => 1800,
            'financial_transaction_id' => $financialTransaction->id,
        ]);
        $this->assertDatabaseHas('financial_transactions', [
            'id' => $financialTransaction->id,
            'type' => 'expense',
            'category' => 'animal_purchase',
            'amount' => 1800,
            'farm_id' => $farm->id,
            'animal_id' => $animal->id,
            'reference' => 'FAC-101',
        ]);

        $this->get(route('animal-movements.index', [
            'from' => today()->toDateString(),
            'to' => today()->toDateString(),
        ]))
            ->assertOk()
            ->assertSee('COMP-101')
            ->assertSee('Finca La Esperanza')
            ->assertSee('Potrero Norte');

        $this->get(route('animals.show', $animal))
            ->assertOk()
            ->assertSee('Historial de compras, ventas y traslados')
            ->assertSee('COMP-101')
            ->assertSee('Potrero Norte');
    }

    public function test_animal_sale_marks_animal_sold_and_creates_financial_income(): void
    {
        $farm = Farm::factory()->create();
        $animal = Animal::factory()->create([
            'ear_tag' => 'VENT-202',
            'farm_id' => $farm->id,
            'status' => 'active',
        ]);

        $response = $this->post(route('animal-movements.store'), [
            'movement_type' => 'sale',
            'movement_date' => today()->toDateString(),
            'animal_id' => $animal->id,
            'amount' => 2500,
            'exit_reason' => 'market',
            'counterparty_name' => 'Comprador local',
        ]);

        $response->assertRedirect(route('animal-movements.index', [
            'from' => today()->toDateString(),
            'to' => today()->toDateString(),
        ]));
        $this->assertDatabaseHas('animals', [
            'id' => $animal->id,
            'status' => 'sold',
            'farm_id' => null,
            'paddock_id' => null,
        ]);
        $this->assertDatabaseHas('animal_movements', [
            'movement_type' => 'sale',
            'animal_id' => $animal->id,
            'from_farm_id' => $farm->id,
            'from_farm_name' => $farm->name,
            'exit_reason' => 'market',
            'amount' => 2500,
        ]);
        $this->assertDatabaseHas('financial_transactions', [
            'type' => 'income',
            'category' => 'animal_sales',
            'amount' => 2500,
            'farm_id' => $farm->id,
            'animal_id' => $animal->id,
        ]);
        $this->assertSame(1, FinancialTransaction::query()
            ->where('transaction_date', '>=', today()->toDateString())
            ->where('transaction_date', '<', today()->addDay()->toDateString())
            ->count());

        $this->get(route('finance.index', [
            'from' => today()->toDateString(),
            'to' => today()->toDateString(),
        ]))
            ->assertOk()
            ->assertSee('$2.500,00')
            ->assertSee('Venta de animales');

        $this->delete(route('farms.destroy', $farm))
            ->assertRedirect(route('farms.index'));
        $this->get(route('animal-movements.index'))
            ->assertOk()
            ->assertSee($farm->name);
    }

    public function test_farm_transfer_updates_location_and_does_not_change_cash_flow(): void
    {
        $originFarm = Farm::factory()->create();
        $destinationFarm = Farm::factory()->create();
        $destinationPaddock = Paddock::factory()->create([
            'farm_id' => $destinationFarm->id,
            'name' => 'Potrero Nuevo',
        ]);
        $animal = Animal::factory()->create([
            'farm_id' => $originFarm->id,
            'location' => 'Potrero Viejo',
        ]);

        $response = $this->post(route('animal-movements.store'), [
            'movement_type' => 'farm_transfer',
            'movement_date' => today()->toDateString(),
            'animal_id' => $animal->id,
            'to_farm_id' => $destinationFarm->id,
            'to_paddock_id' => $destinationPaddock->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('animals', [
            'id' => $animal->id,
            'farm_id' => $destinationFarm->id,
            'paddock_id' => $destinationPaddock->id,
            'location' => 'Potrero Nuevo',
        ]);
        $this->assertDatabaseHas('animal_movements', [
            'movement_type' => 'farm_transfer',
            'animal_id' => $animal->id,
            'from_farm_id' => $originFarm->id,
            'to_farm_id' => $destinationFarm->id,
            'from_farm_name' => $originFarm->name,
            'to_farm_name' => $destinationFarm->name,
            'from_location' => 'Potrero Viejo',
            'to_location' => 'Potrero Nuevo',
            'amount' => null,
            'financial_transaction_id' => null,
        ]);
        $this->assertDatabaseCount('financial_transactions', 0);
    }

    public function test_paddock_transfer_updates_the_animals_current_paddock(): void
    {
        $farm = Farm::factory()->create();
        $originPaddock = Paddock::factory()->create(['farm_id' => $farm->id, 'name' => 'Potrero A']);
        $destinationPaddock = Paddock::factory()->create(['farm_id' => $farm->id, 'name' => 'Potrero B']);
        $animal = Animal::factory()->create([
            'farm_id' => $farm->id,
            'paddock_id' => $originPaddock->id,
            'location' => 'Potrero A',
        ]);

        $response = $this->post(route('animal-movements.store'), [
            'movement_type' => 'paddock_transfer',
            'movement_date' => today()->toDateString(),
            'animal_id' => $animal->id,
            'to_paddock_id' => $destinationPaddock->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('animals', [
            'id' => $animal->id,
            'farm_id' => $farm->id,
            'paddock_id' => $destinationPaddock->id,
            'location' => 'Potrero B',
        ]);
        $this->assertDatabaseHas('animal_movements', [
            'movement_type' => 'paddock_transfer',
            'animal_id' => $animal->id,
            'from_paddock_id' => $originPaddock->id,
            'to_paddock_id' => $destinationPaddock->id,
            'from_location' => 'Potrero A',
            'to_location' => 'Potrero B',
        ]);
        $this->assertDatabaseCount('financial_transactions', 0);
    }

    public function test_transfer_rejects_a_paddock_from_another_farm_without_saving(): void
    {
        $animalFarm = Farm::factory()->create();
        $destinationFarm = Farm::factory()->create();
        $otherFarm = Farm::factory()->create();
        $animal = Animal::factory()->create(['farm_id' => $animalFarm->id]);
        $otherPaddock = Paddock::factory()->create(['farm_id' => $otherFarm->id]);

        $response = $this->post(route('animal-movements.store'), [
            'movement_type' => 'farm_transfer',
            'movement_date' => today()->toDateString(),
            'animal_id' => $animal->id,
            'to_farm_id' => $destinationFarm->id,
            'to_paddock_id' => $otherPaddock->id,
        ]);

        $response->assertSessionHasErrors('to_paddock_id');
        $this->assertDatabaseHas('animals', ['id' => $animal->id, 'farm_id' => $animalFarm->id]);
        $this->assertDatabaseCount('animal_movements', 0);
    }

    public function test_farm_transfer_rejects_the_animals_current_farm(): void
    {
        $farm = Farm::factory()->create();
        $animal = Animal::factory()->create(['farm_id' => $farm->id]);

        $response = $this->post(route('animal-movements.store'), [
            'movement_type' => 'farm_transfer',
            'movement_date' => today()->toDateString(),
            'animal_id' => $animal->id,
            'to_farm_id' => $farm->id,
        ]);

        $response->assertSessionHasErrors('to_farm_id');
        $this->assertDatabaseHas('animals', ['id' => $animal->id, 'farm_id' => $farm->id]);
        $this->assertDatabaseCount('animal_movements', 0);
    }

    public function test_sale_requires_an_exit_reason_and_does_not_modify_the_animal(): void
    {
        $animal = Animal::factory()->create(['status' => 'active']);

        $response = $this->post(route('animal-movements.store'), [
            'movement_type' => 'sale',
            'movement_date' => today()->toDateString(),
            'animal_id' => $animal->id,
            'amount' => 500,
        ]);

        $response->assertSessionHasErrors('exit_reason');
        $this->assertDatabaseHas('animals', ['id' => $animal->id, 'status' => 'active']);
        $this->assertDatabaseCount('animal_movements', 0);
        $this->assertDatabaseCount('financial_transactions', 0);
    }

    public function test_movement_form_can_preselect_an_animal_and_transfer_type(): void
    {
        $farm = Farm::factory()->create();
        $animal = Animal::factory()->create([
            'farm_id' => $farm->id,
            'ear_tag' => 'MOVE-909',
        ]);
        Paddock::factory()->create(['farm_id' => $farm->id, 'name' => 'Potrero Disponible']);

        $response = $this->get(route('animal-movements.create', [
            'animal_id' => $animal->id,
            'movement_type' => 'paddock_transfer',
        ]));

        $response->assertOk()
            ->assertSee('Registrar movimiento')
            ->assertSee('MOVE-909')
            ->assertSee('Potrero Disponible')
            ->assertSee('value="paddock_transfer" selected', false);
    }
}
