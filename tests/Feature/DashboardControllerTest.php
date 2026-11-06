<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\AnimalMovement;
use App\Models\Farm;
use App\Models\FinancialTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_key_farm_metrics_production_and_recent_activity(): void
    {
        $this->freezeTime();
        $farm = Farm::factory()->create(['name' => 'Finca El Roble']);
        $cow = Animal::factory()->create([
            'farm_id' => $farm->id,
            'ear_tag' => 'DASH-101',
            'status' => 'active',
            'reproductive_status' => 'pregnant',
            'expected_calving_date' => today()->addDays(8)->toDateString(),
            'current_weight_kg' => 412,
        ]);
        $this->assertDatabaseHas('animals', [
            'farm_id' => $farm->id,
            'ear_tag' => 'DASH-101',
            'status' => 'active',
        ]);
        $cow->milkProductionRecords()->create([
            'record_date' => today()->toDateString(),
            'morning_liters' => 10,
            'evening_liters' => 8,
        ]);
        $cow->reproductiveEvents()->create([
            'event_type' => 'pregnancy_diagnosis',
            'event_date' => today()->subDays(3)->toDateString(),
            'diagnosis_result' => 'pregnant',
            'expected_calving_date' => today()->addDays(8)->toDateString(),
        ]);
        FinancialTransaction::factory()->create([
            'type' => 'income',
            'category' => 'milk_sales',
            'amount' => 1500,
            'transaction_date' => today()->toDateString(),
            'farm_id' => $farm->id,
        ]);
        FinancialTransaction::factory()->create([
            'type' => 'expense',
            'category' => 'feed',
            'amount' => 500,
            'transaction_date' => today()->toDateString(),
            'farm_id' => $farm->id,
        ]);

        $response = $this->get(route('dashboard', ['farm_id' => $farm->id]));

        $response->assertOk()
            ->assertSee('Tu finca, en un solo vistazo.')
            ->assertSee('Finca El Roble')
            ->assertSee('DASH-101')
            ->assertSee('18,0')
            ->assertSee('412,0 kg de peso promedio')
            ->assertSee('$1.000')
            ->assertSee('Producción de leche')
            ->assertSee('Calendario de partos')
            ->assertSee('id="milk-area-gradient"', false);
    }

    public function test_dashboard_farm_filter_excludes_other_farms_from_metrics_and_charts(): void
    {
        $this->freezeTime();
        $farm = Farm::factory()->create(['name' => 'Finca Seleccionada']);
        $otherFarm = Farm::factory()->create(['name' => 'Finca Externa']);
        $selectedCow = Animal::factory()->create([
            'farm_id' => $farm->id,
            'ear_tag' => 'DASH-FILTER-101',
            'status' => 'active',
        ]);
        $otherCow = Animal::factory()->create([
            'farm_id' => $otherFarm->id,
            'ear_tag' => 'DASH-FILTER-202',
            'status' => 'active',
        ]);
        $selectedCow->milkProductionRecords()->create([
            'record_date' => today()->toDateString(),
            'morning_liters' => 11,
            'evening_liters' => 0,
        ]);
        $otherCow->milkProductionRecords()->create([
            'record_date' => today()->toDateString(),
            'morning_liters' => 99,
            'evening_liters' => 0,
        ]);
        FinancialTransaction::factory()->create([
            'type' => 'income',
            'category' => 'other',
            'amount' => 10000,
            'transaction_date' => today()->toDateString(),
            'farm_id' => $otherFarm->id,
        ]);

        $response = $this->get(route('dashboard', ['farm_id' => $farm->id]));

        $response->assertOk()
            ->assertSee('11,0')
            ->assertDontSee('99,0')
            ->assertDontSee('$10.000');
    }

    public function test_dashboard_handles_an_empty_farm_without_chart_or_metric_errors(): void
    {
        $farm = Farm::factory()->create(['name' => 'Finca Nueva']);

        $response = $this->get(route('dashboard', ['farm_id' => $farm->id]));

        $response->assertOk()
            ->assertSee('Finca Nueva')
            ->assertSee('Registra tus animales')
            ->assertSee('Cuando registres ordeños');
    }

    public function test_dashboard_rejects_an_archived_farm_filter(): void
    {
        $farm = Farm::factory()->create();
        $farm->delete();

        $response = $this->get(route('dashboard', ['farm_id' => $farm->id]));

        $response->assertSessionHasErrors([
            'farm_id' => 'La finca seleccionada no existe.',
        ]);
    }

    public function test_dashboard_activity_shows_sale_even_when_the_animal_relation_is_missing(): void
    {
        AnimalMovement::query()->create([
            'movement_type' => 'sale',
            'movement_date' => today()->toDateString(),
            'animal_ear_tag' => 'DASH-SOLD-101',
            'amount' => 2200,
        ]);

        $response = $this->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Venta')
            ->assertSee('DASH-SOLD-101');
    }
}
