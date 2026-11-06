<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\Farm;
use App\Models\MilkProductionRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MilkProductionRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_production_is_recorded_with_a_calculated_total(): void
    {
        $cow = Animal::factory()->create(['sex' => 'female', 'ear_tag' => 'VACA-201']);

        $response = $this->post(route('animals.production.store', $cow), [
            'record_date' => today()->subDay()->toDateString(),
            'morning_liters' => 12.25,
            'evening_liters' => 8.5,
            'notes' => 'Ordeño normal',
        ]);

        $record = MilkProductionRecord::query()->firstOrFail();

        $response->assertRedirect(route('animals.production.index', $cow));
        $this->assertDatabaseHas('milk_production_records', [
            'id' => $record->id,
            'animal_id' => $cow->id,
            'morning_liters' => 12.25,
            'evening_liters' => 8.5,
            'total_liters' => 20.75,
        ]);
    }

    public function test_production_history_shows_totals_and_animal_context(): void
    {
        $cow = Animal::factory()->create(['sex' => 'female', 'ear_tag' => 'VACA-202']);
        $cow->productionRecords()->create([
            'record_date' => today()->subDays(2),
            'morning_liters' => 10,
            'evening_liters' => 7.5,
        ]);

        $response = $this->get(route('animals.production.index', $cow));

        $response->assertOk()
            ->assertSee('VACA-202')
            ->assertSee('17,50 L')
            ->assertSee('Producción acumulada');
    }

    public function test_a_cow_cannot_have_duplicate_daily_production_records(): void
    {
        $cow = Animal::factory()->create(['sex' => 'female']);
        $recordDate = today()->subDay()->toDateString();
        $cow->productionRecords()->create([
            'record_date' => $recordDate,
            'morning_liters' => 10,
        ]);

        $response = $this->post(route('animals.production.store', $cow), [
            'record_date' => $recordDate,
            'evening_liters' => 8,
        ]);

        $response->assertSessionHasErrors('record_date');
        $this->assertSame(1, MilkProductionRecord::query()->whereBelongsTo($cow)->count());
    }

    public function test_daily_production_requires_at_least_one_milking(): void
    {
        $cow = Animal::factory()->create(['sex' => 'female']);

        $response = $this->post(route('animals.production.store', $cow), [
            'record_date' => today()->subDay()->toDateString(),
        ]);

        $response->assertSessionHasErrors('morning_liters');
        $this->assertDatabaseCount('milk_production_records', 0);
    }

    public function test_editing_a_production_record_recalculates_its_total(): void
    {
        $cow = Animal::factory()->create(['sex' => 'female']);
        $record = $cow->productionRecords()->create([
            'record_date' => today()->subDays(3),
            'morning_liters' => 10,
            'evening_liters' => 8,
        ]);

        $response = $this->put(route('animals.production.update', [$cow, $record]), [
            'record_date' => today()->subDays(3)->toDateString(),
            'morning_liters' => 11.5,
            'evening_liters' => 9,
        ]);

        $response->assertRedirect(route('animals.production.index', $cow));
        $this->assertDatabaseHas('milk_production_records', [
            'id' => $record->id,
            'total_liters' => 20.5,
        ]);
    }

    public function test_production_record_cannot_be_accessed_under_another_cow(): void
    {
        $cow = Animal::factory()->create(['sex' => 'female']);
        $otherCow = Animal::factory()->create(['sex' => 'female']);
        $record = $cow->productionRecords()->create([
            'record_date' => today()->subDay(),
            'morning_liters' => 10,
        ]);

        $response = $this->get(route('animals.production.edit', [$otherCow, $record]));

        $response->assertNotFound();
        $this->assertModelExists($record);
    }

    public function test_non_bovine_animals_do_not_have_milk_production_pages(): void
    {
        $goat = Animal::factory()->create(['species' => 'goat', 'sex' => 'female']);

        $response = $this->get(route('animals.production.index', $goat));

        $response->assertNotFound();
    }

    public function test_milk_dashboard_filters_totals_and_rows_by_farm_and_date(): void
    {
        $farm = Farm::factory()->create(['name' => 'Finca Central']);
        $otherFarm = Farm::factory()->create(['name' => 'Finca Norte']);
        $cow = Animal::factory()->create([
            'farm_id' => $farm->id,
            'ear_tag' => 'VACA-PANEL-1',
            'sex' => 'female',
        ]);
        $otherCow = Animal::factory()->create([
            'farm_id' => $otherFarm->id,
            'ear_tag' => 'VACA-PANEL-2',
            'sex' => 'female',
        ]);
        $recordDate = today()->subDays(3)->toDateString();
        $cow->productionRecords()->create([
            'record_date' => $recordDate,
            'morning_liters' => 15,
            'evening_liters' => 10,
        ]);
        $cow->productionRecords()->create([
            'record_date' => today()->subDays(10)->toDateString(),
            'morning_liters' => 9,
        ]);
        $otherCow->productionRecords()->create([
            'record_date' => $recordDate,
            'morning_liters' => 7,
        ]);

        $response = $this->get(route('milk-production.index', [
            'from' => today()->subDays(5)->toDateString(),
            'to' => today()->toDateString(),
            'farm_id' => $farm->id,
        ]));

        $response->assertOk()
            ->assertSee('25,00 L')
            ->assertSee('VACA-PANEL-1')
            ->assertDontSee('href="'.route('animals.production.index', $otherCow).'">VACA-PANEL-2');
    }

    public function test_global_form_registers_daily_production_for_a_selected_cow(): void
    {
        $farm = Farm::factory()->create();
        $cow = Animal::factory()->create([
            'farm_id' => $farm->id,
            'sex' => 'female',
        ]);
        $recordDate = today()->subDay()->toDateString();

        $response = $this->post(route('milk-production.store'), [
            'animal_id' => $cow->id,
            'record_date' => $recordDate,
            'morning_liters' => 14.25,
            'evening_liters' => 9.5,
            'notes' => 'Ordeño completo',
        ]);

        $response->assertRedirect(route('milk-production.index', [
            'from' => $recordDate,
            'to' => $recordDate,
            'animal_id' => $cow->id,
        ]));
        $this->assertDatabaseHas('milk_production_records', [
            'animal_id' => $cow->id,
            'total_liters' => 23.75,
            'notes' => 'Ordeño completo',
        ]);
        $record = MilkProductionRecord::query()->whereBelongsTo($cow)->firstOrFail();
        $this->assertModelExists($record);
        $this->assertSame($recordDate, $record->record_date->toDateString());
    }

    public function test_global_form_rejects_an_ineligible_animal_without_creating_a_record(): void
    {
        $bull = Animal::factory()->create(['sex' => 'male']);

        $response = $this->post(route('milk-production.store'), [
            'animal_id' => $bull->id,
            'record_date' => today()->subDay()->toDateString(),
            'morning_liters' => 10,
        ]);

        $response->assertSessionHasErrors('animal_id');
        $this->assertDatabaseCount('milk_production_records', 0);
    }

    public function test_global_form_explains_when_no_active_cows_are_available(): void
    {
        $response = $this->get(route('milk-production.create'));

        $response->assertOk()
            ->assertSee('No hay vacas bovinas activas disponibles para registrar producción.')
            ->assertSee(route('animals.create'));
    }

    public function test_global_form_rejects_duplicate_daily_production(): void
    {
        $cow = Animal::factory()->create(['sex' => 'female']);
        $recordDate = today()->subDay()->toDateString();
        $cow->productionRecords()->create([
            'record_date' => $recordDate,
            'morning_liters' => 10,
        ]);

        $response = $this->post(route('milk-production.store'), [
            'animal_id' => $cow->id,
            'record_date' => $recordDate,
            'evening_liters' => 8,
        ]);

        $response->assertSessionHasErrors('record_date');
        $this->assertDatabaseCount('milk_production_records', 1);
    }

    public function test_dashboard_rejects_a_period_with_end_before_start(): void
    {
        $response = $this->get(route('milk-production.index', [
            'from' => today()->subDays(2)->toDateString(),
            'to' => today()->subDays(3)->toDateString(),
        ]));

        $response->assertSessionHasErrors([
            'to' => 'La fecha final debe ser igual o posterior a la inicial.',
        ]);
    }
}
