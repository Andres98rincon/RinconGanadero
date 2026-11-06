<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\Farm;
use App\Models\Paddock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaddockRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_paddock_can_be_registered_and_filtered_by_farm(): void
    {
        $farm = Farm::factory()->create(['name' => 'Finca Los Robles']);

        $this->get(route('paddocks.create', ['farm_id' => $farm->id]))
            ->assertOk()
            ->assertSee('Registrar potrero')
            ->assertSee('Finca Los Robles');

        $response = $this->post(route('paddocks.store'), [
            'farm_id' => $farm->id,
            'code' => 'POT-001',
            'name' => 'Potrero de Cría',
            'area_hectares' => 12.5,
            'capacity' => 40,
            'status' => 'active',
        ]);

        $paddock = Paddock::query()->where('code', 'POT-001')->firstOrFail();
        $response->assertRedirect(route('paddocks.index', ['farm_id' => $farm->id]));
        $this->assertDatabaseHas('paddocks', [
            'id' => $paddock->id,
            'farm_id' => $farm->id,
            'name' => 'Potrero de Cría',
            'area_hectares' => 12.5,
            'capacity' => 40,
        ]);

        $this->get(route('paddocks.index', ['farm_id' => $farm->id]))
            ->assertOk()
            ->assertSee('POT-001')
            ->assertSee('Potrero de Cría');
    }

    public function test_paddock_code_is_unique_within_its_farm(): void
    {
        $farm = Farm::factory()->create();
        Paddock::factory()->create(['farm_id' => $farm->id, 'code' => 'POT-002']);

        $response = $this->post(route('paddocks.store'), [
            'farm_id' => $farm->id,
            'code' => 'POT-002',
            'name' => 'Potrero repetido',
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors('code');
        $this->assertDatabaseCount('paddocks', 1);
    }

    public function test_paddock_with_assigned_animals_cannot_be_archived(): void
    {
        $paddock = Paddock::factory()->create();
        Animal::factory()->create([
            'farm_id' => $paddock->farm_id,
            'paddock_id' => $paddock->id,
        ]);

        $response = $this->delete(route('paddocks.destroy', $paddock));

        $response->assertSessionHasErrors('paddock');
        $this->assertModelExists($paddock);
    }

    public function test_farm_with_registered_paddocks_cannot_be_archived(): void
    {
        $paddock = Paddock::factory()->create();

        $response = $this->delete(route('farms.destroy', $paddock->farm));

        $response->assertSessionHasErrors('farm');
        $this->assertModelExists($paddock->farm);
    }

    public function test_farm_paddock_options_only_return_active_paddocks_for_that_farm(): void
    {
        $farm = Farm::factory()->create();
        $otherFarm = Farm::factory()->create();
        $activePaddock = Paddock::factory()->create(['farm_id' => $farm->id, 'name' => 'Potrero activo']);
        Paddock::factory()->create(['farm_id' => $farm->id, 'status' => 'inactive']);
        Paddock::factory()->create(['farm_id' => $otherFarm->id]);

        $response = $this->getJson(route('farms.paddock-options', $farm));

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['id' => $activePaddock->id, 'name' => 'Potrero activo']);
    }
}
