<?php

namespace Tests\Feature;

use App\Models\Farm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FarmGeolocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_farm_coordinates_are_saved_and_displayed_on_map(): void
    {
        $this->post(route('farms.store'), [
            'code' => 'GEO-001',
            'name' => 'Finca Mapa',
            'status' => 'active',
            'latitude' => '4.5709000',
            'longitude' => '-74.2973000',
        ])->assertRedirect();

        $farm = Farm::query()->where('code', 'GEO-001')->firstOrFail();
        $this->assertSame('4.5709000', $farm->latitude);
        $this->assertSame('-74.2973000', $farm->longitude);
        $this->get(route('farms.show', $farm))
            ->assertOk()
            ->assertSee('data-farm-map', false)
            ->assertSee('4.5709000', false)
            ->assertSee('-74.2973000', false);
    }

    public function test_coordinates_are_checked_against_geographic_bounds(): void
    {
        $this->from(route('farms.create'))
            ->post(route('farms.store'), [
                'code' => 'GEO-002', 'name' => 'Finca inválida', 'status' => 'active',
                'latitude' => 91, 'longitude' => 181,
            ])
            ->assertSessionHasErrors(['latitude', 'longitude']);
    }

    public function test_farm_form_exposes_openstreetmap_picker(): void
    {
        $this->get(route('farms.create'))
            ->assertOk()
            ->assertSee('data-map-mode="picker"', false)
            ->assertSee('data-farm-latitude', false)
            ->assertSee('OpenStreetMap', false);
    }
}
