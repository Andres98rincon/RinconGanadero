<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\AnimalValuation;
use App\Models\Farm;
use App\Models\FinancialTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialDashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_calculates_cash_flow_profitability_and_current_inventory_value(): void
    {
        $this->freezeTime();
        $farm = Farm::factory()->create(['name' => 'Finca El Prado']);
        $animal = Animal::factory()->create([
            'farm_id' => $farm->id,
            'ear_tag' => 'FIN-VAL-101',
            'status' => 'active',
        ]);
        FinancialTransaction::factory()->create([
            'type' => 'income',
            'category' => 'milk_sales',
            'title' => 'Venta de leche',
            'amount' => 400,
            'transaction_date' => today()->subDays(3)->toDateString(),
            'farm_id' => $farm->id,
        ]);
        FinancialTransaction::factory()->create([
            'type' => 'expense',
            'category' => 'feed',
            'title' => 'Compra de alimento',
            'amount' => 100,
            'transaction_date' => today()->subDays(2)->toDateString(),
            'farm_id' => $farm->id,
            'animal_id' => $animal->id,
        ]);
        FinancialTransaction::factory()->create([
            'type' => 'expense',
            'category' => 'veterinary',
            'title' => 'Gasto fuera del período',
            'amount' => 500,
            'transaction_date' => today()->subDays(40)->toDateString(),
            'farm_id' => $farm->id,
        ]);
        $animal->valuations()->create([
            'amount' => 1000,
            'valuation_date' => today()->subDays(10)->toDateString(),
        ]);
        $animal->valuations()->create([
            'amount' => 1200,
            'valuation_date' => today()->subDay()->toDateString(),
        ]);
        $inactiveAnimal = Animal::factory()->create([
            'farm_id' => $farm->id,
            'status' => 'sold',
        ]);
        $inactiveAnimal->valuations()->create([
            'amount' => 900,
            'valuation_date' => today()->subDay()->toDateString(),
        ]);
        Animal::factory()->create([
            'farm_id' => $farm->id,
            'ear_tag' => 'FIN-NOVAL-202',
            'status' => 'active',
        ]);

        $response = $this->get(route('finance.index', [
            'from' => today()->subDays(5)->toDateString(),
            'to' => today()->toDateString(),
            'farm_id' => $farm->id,
        ]));

        $response->assertOk()
            ->assertSee('$400,00')
            ->assertSee('$100,00')
            ->assertSee('$300,00')
            ->assertSee('$1.200,00')
            ->assertSee('FIN-VAL-101')
            ->assertDontSee('FIN-NOVAL-202')
            ->assertDontSee('Gasto fuera del período');
    }

    public function test_dashboard_search_is_bound_and_cannot_expand_financial_results(): void
    {
        FinancialTransaction::factory()->create([
            'type' => 'income',
            'category' => 'other',
            'title' => 'Ingreso seguro',
            'transaction_date' => today()->subDay()->toDateString(),
        ]);

        $response = $this->get(route('finance.index', ['q' => "x%' OR 1=1 --"]));

        $response->assertOk()
            ->assertSee('No hay movimientos con esos filtros.')
            ->assertDontSee('Ingreso seguro');
    }
}
