<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\Farm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FarmRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_farm_inventory_lists_and_filters_by_name(): void
    {
        $matchingFarm = Farm::factory()->create(['code' => 'FIN-001', 'name' => 'La Esperanza']);
        Farm::factory()->create(['code' => 'FIN-002', 'name' => 'El Roble']);

        $response = $this->get(route('farms.index', ['q' => 'Esperanza']));

        $response->assertOk()->assertSee('FIN-001')->assertSee('La Esperanza')->assertDontSee('FIN-002');
        $this->assertModelExists($matchingFarm);
    }

    public function test_new_farm_form_exposes_required_identification_fields(): void
    {
        $response = $this->get(route('farms.create'));

        $response->assertOk()->assertSee('Código de finca')->assertSee('Nombre de la finca');
    }

    public function test_farm_can_be_registered_with_a_unique_code(): void
    {
        $response = $this->post(route('farms.store'), $this->validFarmData());

        $farm = Farm::query()->where('code', 'FIN-003')->firstOrFail();

        $response->assertRedirect(route('farms.show', $farm));
        $this->assertDatabaseHas('farms', [
            'code' => 'FIN-003',
            'name' => 'El Paraíso',
            'municipality' => 'Pitalito',
            'area_hectares' => 125.5,
            'status' => 'active',
        ]);
    }

    public function test_required_farm_fields_are_validated_without_saving(): void
    {
        $response = $this->post(route('farms.store'), []);

        $response->assertSessionHasErrors(['code', 'name', 'status']);
        $this->assertDatabaseCount('farms', 0);
    }

    public function test_farm_code_must_be_unique(): void
    {
        Farm::factory()->create(['code' => 'FIN-004']);

        $response = $this->post(route('farms.store'), $this->validFarmData(['code' => 'FIN-004']));

        $response->assertSessionHasErrors('code');
        $this->assertDatabaseCount('farms', 1);
    }

    public function test_farm_can_be_updated_without_changing_its_code(): void
    {
        $farm = Farm::factory()->create(['code' => 'FIN-005']);

        $response = $this->put(route('farms.update', $farm), $this->validFarmData([
            'code' => 'FIN-005',
            'name' => 'El Paraíso renovada',
        ]));

        $response->assertRedirect(route('farms.show', $farm));
        $this->assertDatabaseHas('farms', ['id' => $farm->id, 'name' => 'El Paraíso renovada']);
    }

    public function test_farm_detail_displays_associated_animals(): void
    {
        $farm = Farm::factory()->create(['name' => 'La Esperanza']);
        $animal = Animal::factory()->create([
            'farm_id' => $farm->id,
            'ear_tag' => 'HATO-110',
            'location' => 'Potrero alto',
        ]);

        $response = $this->get(route('farms.show', $farm));

        $response->assertOk()
            ->assertSee('La Esperanza')
            ->assertSee('HATO-110')
            ->assertSee('Potrero alto')
            ->assertSee(route('animals.show', $animal));
    }

    public function test_farm_with_animals_cannot_be_archived(): void
    {
        $farm = Farm::factory()->create();
        Animal::factory()->create(['farm_id' => $farm->id]);

        $response = $this->delete(route('farms.destroy', $farm));

        $response->assertSessionHasErrors('farm');
        $this->assertModelExists($farm);
    }

    public function test_empty_farm_can_be_archived(): void
    {
        $farm = Farm::factory()->create();

        $response = $this->delete(route('farms.destroy', $farm));

        $response->assertRedirect(route('farms.index'));
        $this->assertSoftDeleted($farm);
    }

    private function validFarmData(array $overrides = []): array
    {
        return array_merge([
            'code' => 'FIN-003',
            'name' => 'El Paraíso',
            'municipality' => 'Pitalito',
            'province' => 'Huila',
            'area_hectares' => 125.5,
            'manager_name' => 'María Gómez',
            'status' => 'active',
        ], $overrides);
    }
}
