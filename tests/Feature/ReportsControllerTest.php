<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\AnimalMovement;
use App\Models\Farm;
use App\Models\FinancialTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_report_shows_active_animals_and_the_latest_non_future_valuation(): void
    {
        $this->freezeTime();
        $farm = Farm::factory()->create(['name' => 'Finca Reportes']);
        $animal = Animal::factory()->create([
            'farm_id' => $farm->id,
            'ear_tag' => 'REP-INV-101',
            'status' => 'active',
            'current_weight_kg' => 320,
        ]);
        $animal->valuations()->createMany([
            ['amount' => 900, 'valuation_date' => today()->subDays(10)->toDateString()],
            ['amount' => 1200, 'valuation_date' => today()->subDay()->toDateString()],
            ['amount' => 1800, 'valuation_date' => today()->addDay()->toDateString()],
        ]);
        Animal::factory()->create(['ear_tag' => 'REP-INV-SOLD', 'status' => 'sold', 'farm_id' => $farm->id]);
        Animal::factory()->create(['ear_tag' => 'REP-INV-OTHER']);

        $response = $this->get(route('reports.index', [
            'report' => 'inventory',
            'farm_id' => $farm->id,
        ]));

        $response->assertOk()
            ->assertSee('REP-INV-101')
            ->assertSee('1.200,00')
            ->assertDontSee('REP-INV-SOLD')
            ->assertDontSee('REP-INV-OTHER');
    }

    public function test_reports_dashboard_shows_an_empty_state_when_no_inventory_exists(): void
    {
        $response = $this->get(route('reports.index'));

        $response->assertOk()
            ->assertSee('Inventario actual')
            ->assertSee('No hay datos para los filtros seleccionados.');
    }

    public function test_milk_report_aggregates_records_for_the_selected_period_and_farm(): void
    {
        $this->freezeTime();
        $farm = Farm::factory()->create(['name' => 'Finca Lechera']);
        $cow = Animal::factory()->create(['farm_id' => $farm->id, 'ear_tag' => 'REP-LECHE-101']);
        $otherCow = Animal::factory()->create(['ear_tag' => 'REP-LECHE-202']);
        $cow->milkProductionRecords()->create([
            'record_date' => today()->subDays(4)->toDateString(),
            'morning_liters' => 8,
            'evening_liters' => 6,
        ]);
        $cow->milkProductionRecords()->create([
            'record_date' => today()->subDays(2)->toDateString(),
            'morning_liters' => 7,
            'evening_liters' => 9,
        ]);
        $cow->milkProductionRecords()->create([
            'record_date' => today()->subDays(40)->toDateString(),
            'morning_liters' => 100,
            'evening_liters' => 100,
        ]);
        $otherCow->milkProductionRecords()->create([
            'record_date' => today()->subDays(2)->toDateString(),
            'morning_liters' => 50,
            'evening_liters' => 50,
        ]);

        $response = $this->get(route('reports.index', [
            'report' => 'milk',
            'from' => today()->subDays(5)->toDateString(),
            'to' => today()->toDateString(),
            'farm_id' => $farm->id,
        ]));

        $response->assertOk()
            ->assertSee('REP-LECHE-101')
            ->assertSee('30,00')
            ->assertSee('15,00')
            ->assertDontSee('REP-LECHE-202');
    }

    public function test_reproduction_report_summarizes_events_and_lists_registered_calves(): void
    {
        $this->freezeTime();
        $farm = Farm::factory()->create(['name' => 'Finca Reproductora']);
        $cow = Animal::factory()->create(['farm_id' => $farm->id, 'ear_tag' => 'REP-REPRO-101']);
        $cow->reproductiveEvents()->create([
            'event_type' => 'pregnancy_diagnosis',
            'event_date' => today()->subDays(4)->toDateString(),
            'diagnosis_result' => 'pregnant',
            'expected_calving_date' => today()->addDays(250)->toDateString(),
        ]);
        $calving = $cow->reproductiveEvents()->create([
            'event_type' => 'calving',
            'event_date' => today()->subDay()->toDateString(),
        ]);
        $calf = Animal::factory()->create(['ear_tag' => 'REP-REPRO-CALF']);
        $calving->calves()->attach($calf);
        $cow->reproductiveEvents()->create([
            'event_type' => 'abortion',
            'event_date' => today()->subDays(2)->toDateString(),
        ]);

        $response = $this->get(route('reports.index', [
            'report' => 'reproduction',
            'from' => today()->subDays(5)->toDateString(),
            'to' => today()->toDateString(),
            'farm_id' => $farm->id,
        ]));

        $response->assertOk()
            ->assertSee('Diagnóstico de preñez')
            ->assertSee('Preñada')
            ->assertSee('Parto')
            ->assertSee('REP-REPRO-101')
            ->assertSee('1 aborto registrado');
    }

    public function test_weight_report_lists_only_weighing_events_in_the_selected_period(): void
    {
        $this->freezeTime();
        $farm = Farm::factory()->create(['name' => 'Finca de Pesaje']);
        $animal = Animal::factory()->create(['farm_id' => $farm->id, 'ear_tag' => 'REP-PESO-101']);
        $animal->events()->create([
            'event_type' => 'weighing',
            'event_date' => today()->subDays(3)->toDateString(),
            'title' => 'Pesaje de control',
            'weight_kg' => 285.5,
            'description' => 'Control trimestral',
        ]);
        $animal->events()->create([
            'event_type' => 'weighing',
            'event_date' => today()->subDays(40)->toDateString(),
            'title' => 'Pesaje fuera de período',
            'weight_kg' => 270,
        ]);
        $animal->events()->create([
            'event_type' => 'health',
            'event_date' => today()->subDays(3)->toDateString(),
            'title' => 'Control veterinario',
        ]);

        $response = $this->get(route('reports.index', [
            'report' => 'weights',
            'from' => today()->subDays(5)->toDateString(),
            'to' => today()->toDateString(),
            'farm_id' => $farm->id,
        ]));

        $response->assertOk()
            ->assertSee('REP-PESO-101')
            ->assertSee('285,50')
            ->assertSee('Control trimestral')
            ->assertDontSee('Pesaje fuera de período')
            ->assertDontSee('Control veterinario');
    }

    public function test_movements_report_includes_transfers_for_either_selected_farm(): void
    {
        $this->freezeTime();
        $origin = Farm::factory()->create(['name' => 'Finca Origen']);
        $destination = Farm::factory()->create(['name' => 'Finca Destino']);
        AnimalMovement::query()->create([
            'movement_type' => 'farm_transfer',
            'movement_date' => today()->subDay()->toDateString(),
            'animal_ear_tag' => 'REP-MOV-101',
            'from_farm_id' => $origin->id,
            'from_farm_name' => $origin->name,
            'to_farm_id' => $destination->id,
            'to_farm_name' => $destination->name,
            'amount' => null,
        ]);

        $response = $this->get(route('reports.index', [
            'report' => 'movements',
            'from' => today()->subDays(5)->toDateString(),
            'to' => today()->toDateString(),
            'farm_id' => $destination->id,
        ]));

        $response->assertOk()
            ->assertSee('REP-MOV-101')
            ->assertSee('Finca Origen')
            ->assertSee('Finca Destino')
            ->assertSee('Traslado entre fincas');
    }

    public function test_finance_report_totals_income_and_expenses_by_category_and_farm(): void
    {
        $this->freezeTime();
        $farm = Farm::factory()->create(['name' => 'Finca Financiera']);
        FinancialTransaction::factory()->create([
            'type' => 'income',
            'category' => 'milk_sales',
            'amount' => 1000,
            'transaction_date' => today()->subDay()->toDateString(),
            'farm_id' => $farm->id,
        ]);
        FinancialTransaction::factory()->create([
            'type' => 'expense',
            'category' => 'feed',
            'amount' => 350,
            'transaction_date' => today()->subDay()->toDateString(),
            'farm_id' => $farm->id,
        ]);
        FinancialTransaction::factory()->create([
            'type' => 'income',
            'category' => 'other',
            'amount' => 9000,
            'transaction_date' => today()->subDay()->toDateString(),
        ]);

        $response = $this->get(route('reports.index', [
            'report' => 'finance',
            'from' => today()->subDays(5)->toDateString(),
            'to' => today()->toDateString(),
            'farm_id' => $farm->id,
        ]));

        $response->assertOk()
            ->assertSee('Venta de leche')
            ->assertSee('Alimentación')
            ->assertSee('$1.000,00')
            ->assertSee('$350,00')
            ->assertSee('$650,00')
            ->assertDontSee('$9.000,00');
    }

    public function test_csv_export_streams_all_rows_and_protects_spreadsheet_formulas(): void
    {
        $animal = Animal::factory()->create([
            'ear_tag' => 'REP-CSV-101',
            'name' => '=HYPERLINK("https://example.test","open")',
        ]);
        foreach (range(1, 26) as $number) {
            Animal::factory()->create(['ear_tag' => 'REP-CSV-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT)]);
        }

        $response = $this->get(route('reports.export', [
            'report' => 'inventory',
            'from' => today()->startOfMonth()->toDateString(),
            'to' => today()->toDateString(),
        ]));

        $response->assertOk()
            ->assertStreamed()
            ->assertDownload('reporte-inventory-'.today()->startOfMonth()->toDateString().'-'.today()->toDateString().'.csv');
        $this->assertStringContainsString(
            "'=HYPERLINK",
            $response->streamedContent(),
        );
        $this->assertStringContainsString('REP-CSV-101', $response->streamedContent());
        $this->assertStringContainsString('REP-CSV-026', $response->streamedContent());
    }

    public function test_report_filter_rejects_an_unknown_report_and_reversed_period(): void
    {
        $this->get(route('reports.index', ['report' => 'unknown']))
            ->assertSessionHasErrors([
                'report' => 'Selecciona un tipo de reporte válido.',
            ]);

        $this->get(route('reports.index', [
            'report' => 'finance',
            'from' => '2025-02-10',
            'to' => '2025-02-09',
        ]))->assertSessionHasErrors([
            'to' => 'La fecha final debe ser igual o posterior a la inicial.',
        ]);
    }
}
