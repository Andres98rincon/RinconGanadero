<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\AnimalEvent;
use App\Models\AnimalMovement;
use App\Models\Farm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnimalRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_redirects_to_the_dashboard(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('dashboard'));
    }

    public function test_inventory_lists_animals_and_filters_by_tag(): void
    {
        $matchingAnimal = Animal::factory()->create(['ear_tag' => 'FINCA-101']);
        Animal::factory()->create(['ear_tag' => 'FINCA-202']);

        $response = $this->get(route('animals.index', ['q' => 'FINCA-101']));

        $response->assertOk()->assertSee('FINCA-101')->assertDontSee('FINCA-202');
        $this->assertModelExists($matchingAnimal);
    }

    public function test_new_animal_form_exposes_required_identification_fields(): void
    {
        $response = $this->get(route('animals.create'));

        $response->assertOk()->assertSee('Número de arete / identificación')->assertSee('Especie');
    }

    public function test_animal_can_be_assigned_to_a_farm(): void
    {
        $farm = Farm::factory()->create(['code' => 'FIN-021', 'name' => 'Los Robles']);

        $response = $this->post(route('animals.store'), $this->validAnimalData([
            'farm_id' => $farm->id,
        ]));

        $animal = Animal::query()->where('ear_tag', 'FINCA-303')->firstOrFail();

        $response->assertRedirect(route('animals.show', $animal));
        $this->assertDatabaseHas('animals', [
            'id' => $animal->id,
            'farm_id' => $farm->id,
        ]);
    }

    public function test_registering_an_animal_from_a_farm_preselects_its_location(): void
    {
        $farm = Farm::factory()->create(['code' => 'FIN-022', 'name' => 'Los Robles']);

        $response = $this->get(route('animals.create', ['farm_id' => $farm->id]));

        $response->assertOk()
            ->assertSee('value="'.$farm->id.'" selected', false)
            ->assertSee('Los Robles');
    }

    public function test_animal_inventory_can_be_filtered_by_farm(): void
    {
        $farm = Farm::factory()->create(['name' => 'Los Robles']);
        Animal::factory()->create(['ear_tag' => 'FINCA-701', 'farm_id' => $farm->id]);
        Animal::factory()->create(['ear_tag' => 'FINCA-702']);

        $response = $this->get(route('animals.index', ['farm_id' => $farm->id]));

        $response->assertOk()->assertSee('FINCA-701')->assertDontSee('FINCA-702');
    }

    public function test_animal_cannot_be_assigned_to_an_archived_farm(): void
    {
        $farm = Farm::factory()->create();
        $farm->delete();

        $response = $this->post(route('animals.store'), $this->validAnimalData([
            'farm_id' => $farm->id,
        ]));

        $response->assertSessionHasErrors('farm_id');
        $this->assertDatabaseMissing('animals', ['ear_tag' => 'FINCA-303']);
    }

    public function test_animal_detail_displays_its_record_and_event_history(): void
    {
        $animal = Animal::factory()->create(['ear_tag' => 'FINCA-606']);
        $animal->events()->create([
            'event_type' => 'vaccination',
            'event_date' => '2025-03-10',
            'title' => 'Vacuna clostridial',
        ]);

        $response = $this->get(route('animals.show', $animal));

        $response->assertOk()->assertSee('FINCA-606')->assertSee('Vacuna clostridial')->assertSee('Bitácora de campo');
    }

    public function test_animal_can_be_registered_with_a_unique_identification(): void
    {
        $response = $this->post(route('animals.store'), $this->validAnimalData());

        $animal = Animal::query()->where('ear_tag', 'FINCA-303')->firstOrFail();

        $response->assertRedirect(route('animals.show', $animal));
        $this->assertDatabaseHas('animals', [
            'ear_tag' => 'FINCA-303',
            'species' => 'bovine',
            'sex' => 'female',
            'status' => 'active',
            'location' => 'Potrero norte',
        ]);
    }

    public function test_required_fields_are_validated_without_saving_animal(): void
    {
        $response = $this->post(route('animals.store'), []);

        $response->assertSessionHasErrors(['ear_tag', 'species', 'sex', 'status', 'acquisition_type']);
        $this->assertDatabaseCount('animals', 0);
    }

    public function test_animal_identification_must_be_unique(): void
    {
        Animal::factory()->create(['ear_tag' => 'FINCA-404']);

        $response = $this->post(route('animals.store'), $this->validAnimalData(['ear_tag' => 'FINCA-404']));

        $response->assertSessionHasErrors('ear_tag');
        $this->assertDatabaseCount('animals', 1);
    }

    public function test_animal_can_be_updated_without_changing_its_identification(): void
    {
        $animal = Animal::factory()->create(['ear_tag' => 'FINCA-505']);

        $response = $this->put(route('animals.update', $animal), $this->validAnimalData([
            'ear_tag' => 'FINCA-505',
            'name' => 'Lucera II',
        ]));

        $response->assertRedirect(route('animals.show', $animal));
        $this->assertDatabaseHas('animals', ['id' => $animal->id, 'name' => 'Lucera II']);
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Animal::class,
            'subject_id' => $animal->id,
            'event' => 'updated',
        ]);
    }

    public function test_animal_location_with_movement_history_must_be_changed_through_the_movement_module(): void
    {
        $originFarm = Farm::factory()->create();
        $destinationFarm = Farm::factory()->create();
        $animal = Animal::factory()->create([
            'farm_id' => $originFarm->id,
            'location' => 'Potrero norte',
        ]);
        AnimalMovement::query()->create([
            'movement_type' => 'farm_transfer',
            'movement_date' => today()->toDateString(),
            'animal_id' => $animal->id,
            'animal_ear_tag' => $animal->ear_tag,
            'from_farm_id' => $originFarm->id,
            'to_farm_id' => $originFarm->id,
        ]);

        $response = $this->put(route('animals.update', $animal), $this->validAnimalData([
            'ear_tag' => $animal->ear_tag,
            'farm_id' => $destinationFarm->id,
            'location' => 'Potrero sur',
        ]));

        $response->assertSessionHasErrors('farm_id');
        $this->assertDatabaseHas('animals', [
            'id' => $animal->id,
            'farm_id' => $originFarm->id,
            'location' => 'Potrero norte',
        ]);
    }

    public function test_animal_cannot_be_marked_as_sold_without_registering_a_sale(): void
    {
        $animal = Animal::factory()->create(['status' => 'active']);

        $response = $this->put(route('animals.update', $animal), $this->validAnimalData([
            'ear_tag' => $animal->ear_tag,
            'status' => 'sold',
        ]));

        $response->assertSessionHasErrors('status');
        $this->assertDatabaseHas('animals', ['id' => $animal->id, 'status' => 'active']);
        $this->assertDatabaseCount('financial_transactions', 0);
    }

    public function test_purchase_price_cannot_be_changed_outside_its_movement_and_financial_history(): void
    {
        $animal = Animal::factory()->create([
            'acquisition_type' => 'purchased',
            'acquisition_date' => today()->subDays(5)->toDateString(),
            'acquisition_cost' => 1500,
            'supplier_name' => 'Proveedor original',
        ]);
        AnimalMovement::query()->create([
            'movement_type' => 'purchase',
            'movement_date' => today()->subDays(5)->toDateString(),
            'animal_id' => $animal->id,
            'animal_ear_tag' => $animal->ear_tag,
            'amount' => 1500,
        ]);

        $response = $this->put(route('animals.update', $animal), $this->validAnimalData([
            'ear_tag' => $animal->ear_tag,
            'acquisition_type' => 'purchased',
            'acquisition_date' => today()->subDays(5)->toDateString(),
            'acquisition_cost' => 2500,
            'supplier_name' => 'Proveedor original',
        ]));

        $response->assertSessionHasErrors('acquisition_cost');
        $this->assertDatabaseHas('animals', ['id' => $animal->id, 'acquisition_cost' => 1500]);
    }

    public function test_parent_must_be_the_correct_sex_and_species(): void
    {
        $father = Animal::factory()->create(['sex' => 'male']);

        $response = $this->post(route('animals.store'), $this->validAnimalData(['dam_id' => $father->id]));

        $response->assertSessionHasErrors('dam_id');
        $this->assertDatabaseMissing('animals', ['ear_tag' => 'FINCA-303']);
    }

    public function test_weighing_event_updates_current_weight_and_keeps_history(): void
    {
        $animal = Animal::factory()->create();

        $response = $this->post(route('animals.events.store', $animal), [
            'event_type' => 'weighing',
            'event_date' => '2025-06-15',
            'title' => 'Pesaje de control',
            'weight_kg' => 348.5,
        ]);

        $response->assertRedirect(route('animals.show', $animal));
        $this->assertDatabaseHas('animal_events', [
            'animal_id' => $animal->id,
            'event_type' => 'weighing',
            'weight_kg' => 348.5,
        ]);
        $this->assertSame('348.50', $animal->fresh()->current_weight_kg);
        $this->assertSame(1, AnimalEvent::query()->whereBelongsTo($animal)->count());
    }

    public function test_deleting_latest_weighing_event_restores_the_previous_weight(): void
    {
        $animal = Animal::factory()->create();
        $previousEvent = $animal->events()->create([
            'event_type' => 'weighing',
            'event_date' => '2025-01-10',
            'title' => 'Pesaje anterior',
            'weight_kg' => 320,
        ]);
        $latestEvent = $animal->events()->create([
            'event_type' => 'weighing',
            'event_date' => '2025-02-10',
            'title' => 'Pesaje reciente',
            'weight_kg' => 340,
        ]);

        $response = $this->delete(route('animals.events.destroy', [$animal, $latestEvent]));

        $response->assertRedirect(route('animals.show', $animal));
        $this->assertModelExists($previousEvent);
        $this->assertSame('320.00', $animal->fresh()->current_weight_kg);
        $this->assertSame('2025-01-10', $animal->fresh()->last_weighed_at->toDateString());
    }

    public function test_animal_event_cannot_be_deleted_through_another_animals_url(): void
    {
        $animal = Animal::factory()->create();
        $otherAnimal = Animal::factory()->create();
        $event = $animal->events()->create([
            'event_type' => 'other',
            'event_date' => '2025-02-10',
            'title' => 'Nota',
        ]);

        $response = $this->delete(route('animals.events.destroy', [$otherAnimal, $event]));

        $response->assertNotFound();
        $this->assertModelExists($event);
    }

    public function test_removing_an_animal_archives_its_record(): void
    {
        $animal = Animal::factory()->create();

        $response = $this->delete(route('animals.destroy', $animal));

        $response->assertRedirect(route('animals.index'));
        $this->assertSoftDeleted($animal);
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Animal::class,
            'subject_id' => $animal->id,
            'event' => 'deleted',
        ]);
    }

    private function validAnimalData(array $overrides = []): array
    {
        return array_merge([
            'ear_tag' => 'FINCA-303',
            'name' => 'Lucera',
            'species' => 'bovine',
            'sex' => 'female',
            'identification_method' => 'visual',
            'birth_date' => '2024-03-10',
            'purpose' => 'dual',
            'status' => 'active',
            'acquisition_type' => 'born',
            'location' => 'Potrero norte',
        ], $overrides);
    }
}
