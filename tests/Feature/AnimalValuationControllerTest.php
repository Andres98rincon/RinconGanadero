<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\AnimalValuation;
use App\Models\FinancialTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnimalValuationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_valuation_is_saved_without_changing_cash_flow(): void
    {
        $this->freezeTime();
        $animal = Animal::factory()->create();

        $response = $this->post(route('finance.valuations.store'), [
            'animal_id' => $animal->id,
            'valuation_date' => today()->toDateString(),
            'amount' => 7500,
            'notes' => 'Valor de mercado estimado',
        ]);

        $valuation = AnimalValuation::query()->firstOrFail();
        $response->assertRedirect(route('finance.index', [
            'from' => today()->startOfMonth()->toDateString(),
            'to' => today()->toDateString(),
            'animal_id' => $animal->id,
        ]));
        $this->assertModelExists($valuation);
        $this->assertSame(0, FinancialTransaction::query()->count());
    }

    public function test_inventory_values_use_the_latest_valuation_in_effective_date_order(): void
    {
        $this->freezeTime();
        $animal = Animal::factory()->create(['status' => 'active']);
        $animal->valuations()->create([
            'amount' => 9000,
            'valuation_date' => today()->subDays(5)->toDateString(),
        ]);
        $animal->valuations()->create([
            'amount' => 6000,
            'valuation_date' => today()->subDays(10)->toDateString(),
        ]);
        $animal->valuations()->create([
            'amount' => 12000,
            'valuation_date' => today()->addDay()->toDateString(),
        ]);

        $response = $this->get(route('finance.index'));

        $response->assertOk()
            ->assertSee('$9.000,00')
            ->assertDontSee('$6.000,00')
            ->assertDontSee('$12.000,00');
    }

    public function test_only_active_animals_can_receive_new_valuations(): void
    {
        $animal = Animal::factory()->create(['status' => 'sold']);

        $response = $this->post(route('finance.valuations.store'), [
            'animal_id' => $animal->id,
            'valuation_date' => today()->subDay()->toDateString(),
            'amount' => 12000,
        ]);

        $response->assertSessionHasErrors([
            'animal_id' => 'La valoración solo se puede registrar para animales activos.',
        ]);
        $this->assertDatabaseCount('animal_valuations', 0);
    }

    public function test_new_valuation_compares_against_the_previous_value_and_create_form_shows_history(): void
    {
        $this->freezeTime();
        $animal = Animal::factory()->create(['name' => 'Lucera']);
        $animal->valuations()->create([
            'amount' => 10000,
            'valuation_date' => today()->subMonth()->toDateString(),
            'notes' => 'Valor inicial',
        ]);

        $this->get(route('finance.valuations.create', ['animal_id' => $animal->id]))
            ->assertOk()
            ->assertSee('$10.000,00')
            ->assertSee('Valor inicial');

        $this->post(route('finance.valuations.store'), [
            'animal_id' => $animal->id,
            'valuation_date' => today()->toDateString(),
            'amount' => 12500,
        ])->assertSessionHas('valuation_comparison', function (array $comparison): bool {
            return (float) $comparison['previous_amount'] === 10000.0
                && (float) $comparison['new_amount'] === 12500.0
                && (float) $comparison['variation'] === 2500.0;
        });
    }

    public function test_valuation_form_filters_animals_by_the_selected_farm(): void
    {
        $firstFarm = \App\Models\Farm::factory()->create(['name' => 'Finca Norte']);
        $secondFarm = \App\Models\Farm::factory()->create(['name' => 'Finca Sur']);
        $firstAnimal = Animal::factory()->create(['farm_id' => $firstFarm->id, 'ear_tag' => 'NORTE-001']);
        Animal::factory()->create(['farm_id' => $secondFarm->id, 'ear_tag' => 'SUR-001']);

        $this->get(route('finance.valuations.create', ['farm_id' => $firstFarm->id, 'q' => '001']))
            ->assertOk()
            ->assertSee($firstAnimal->ear_tag)
            ->assertDontSee('SUR-001');
    }
}
