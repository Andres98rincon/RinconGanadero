<?php

namespace Tests\Feature;

use App\AccessControl;
use App\Models\Animal;
use App\Models\Breed;
use App\Models\Department;
use App\Models\Farm;
use App\Models\Municipality;
use App\Models\Species;
use App\Models\User;
use Database\Seeders\ColombiaLocationsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SpeciesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CatalogsAndLocationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_base_catalogs_are_available_after_migrating_and_seeders_do_not_duplicate(): void
    {
        $this->assertTrue(Species::query()->where('code', 'bovine')->exists());
        $this->assertSame(33, Department::query()->count());
        $this->assertSame(1122, Municipality::query()->count());

        $this->seed([SpeciesSeeder::class, ColombiaLocationsSeeder::class]);
        $this->seed([SpeciesSeeder::class, ColombiaLocationsSeeder::class]);

        $this->assertSame(1, Species::query()->where('code', 'bovine')->count());
        $this->assertSame(33, Department::query()->count());
        $this->assertSame(1122, Municipality::query()->count());
        $this->assertTrue(Municipality::query()->where('name', 'Pitalito')->whereHas('department', fn ($query) => $query->where('name', 'Huila'))->exists());
    }

    public function test_species_can_be_created_updated_and_only_deleted_when_unused(): void
    {
        $this->post(route('admin.species.store'), ['name' => 'Conejos', 'description' => 'Cunicultura'])
            ->assertRedirect(route('admin.species.index'));
        $species = Species::query()->where('name', 'Conejos')->firstOrFail();
        $this->assertSame('conejos', $species->code);
        $this->assertArrayHasKey('conejos', Species::options());

        $this->put(route('admin.species.update', $species), ['name' => 'Conejo'])->assertRedirect(route('admin.species.index'));
        $this->assertSame('conejos', $species->fresh()->code);

        $breed = Breed::query()->create(['species_id' => $species->id, 'name' => 'Nueva Zelanda']);
        $this->assertSame('conejos', $breed->species);
        $this->delete(route('admin.species.destroy', $species))->assertSessionHas('error');

        $breed->delete();
        $this->delete(route('admin.species.destroy', $species))->assertRedirect(route('admin.species.index'));
        $this->assertModelMissing($species);
    }

    public function test_breeds_belong_to_a_species_and_cannot_be_deleted_with_animals(): void
    {
        $bovine = Species::query()->where('code', 'bovine')->firstOrFail();

        $this->post(route('admin.breeds.store'), ['species_id' => $bovine->id, 'name' => 'Velásquez'])->assertRedirect();
        $breed = Breed::query()->where('name', 'Velásquez')->firstOrFail();
        $this->assertSame('bovine', $breed->species);

        $this->post(route('admin.breeds.store'), ['species_id' => $bovine->id, 'name' => 'Velásquez'])->assertSessionHasErrors('name');

        Animal::factory()->create(['farm_id' => Farm::factory()->create()->id, 'species' => 'bovine', 'breed_id' => $breed->id]);
        $this->delete(route('admin.breeds.destroy', $breed))->assertSessionHas('error');
        $this->assertModelExists($breed);
    }

    public function test_catalog_administration_requires_permission(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $operator = User::factory()->create();
        $operator->assignRole(Role::findByName(AccessControl::OPERATOR_ROLE, 'web'));
        $this->actingAs($operator);

        $this->get(route('admin.species.index'))->assertForbidden();
        $this->get(route('admin.breeds.index'))->assertForbidden();
    }

    public function test_farm_municipality_must_belong_to_the_selected_department(): void
    {
        $base = ['code' => 'FIN-LOC', 'name' => 'La Ubicada', 'status' => 'active'];

        $this->post(route('farms.store'), $base + ['province' => 'Huila', 'municipality' => 'Montería'])->assertSessionHasErrors('municipality');
        $this->post(route('farms.store'), $base + ['province' => 'Narnia', 'municipality' => ''])->assertSessionHasErrors('province');
        $this->post(route('farms.store'), $base + ['province' => 'Córdoba', 'municipality' => 'Montería'])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('farms', ['code' => 'FIN-LOC', 'province' => 'Córdoba', 'municipality' => 'Montería']);
    }

    public function test_farm_form_shows_dependent_location_selects(): void
    {
        $this->get(route('farms.create'))
            ->assertOk()
            ->assertSee('data-depends-on="#province"', false)
            ->assertSee('data-parent="Antioquia"', false)
            ->assertSee('San Pedro de los Milagros');
    }

    public function test_animal_form_breed_select_depends_on_species(): void
    {
        $this->get(route('animals.create'))
            ->assertOk()
            ->assertSee('data-depends-on="#species"', false)
            ->assertSee('Primero selecciona una especie');
    }

    public function test_pagination_shows_a_compact_window_of_pages(): void
    {
        $html = (string) (new LengthAwarePaginator(range(1, 10), 500, 10, 25, ['path' => '/animals']))->links();

        $this->assertStringContainsString('aria-label="Paginación"', $html);
        $this->assertStringContainsString('>24</a>', $html);
        $this->assertStringContainsString('>26</a>', $html);
        $this->assertStringContainsString('>50</a>', $html);
        $this->assertStringNotContainsString('>10</a>', $html);
        $this->assertStringContainsString('…', $html);
    }
}
