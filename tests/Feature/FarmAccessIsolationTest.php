<?php

namespace Tests\Feature;

use App\AccessControl;
use App\Models\Animal;
use App\Models\Farm;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FarmAccessIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_open_an_animal_or_farm_from_an_unassigned_farm(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $farm = Farm::factory()->create();
        $otherFarm = Farm::factory()->create();
        $animal = Animal::factory()->create(['farm_id' => $otherFarm->id]);
        $user = User::factory()->create();
        $user->assignRole(Role::findByName(AccessControl::OPERATOR_ROLE, 'web'));
        $user->farms()->attach($farm);
        $this->actingAs($user);

        $this->get(route('animals.show', $animal))->assertNotFound();
        $this->get(route('farms.show', $otherFarm))->assertNotFound();
    }

    public function test_user_cannot_create_an_animal_in_an_unassigned_farm(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $farm = Farm::factory()->create();
        $otherFarm = Farm::factory()->create();
        $user = User::factory()->create();
        $user->assignRole(Role::findByName(AccessControl::OPERATOR_ROLE, 'web'));
        $user->farms()->attach($farm);
        $this->actingAs($user);

        $this->post(route('animals.store'), [
            'ear_tag' => 'FORBIDDEN-001',
            'species' => 'bovine',
            'sex' => 'female',
            'identification_method' => 'visual',
            'farm_id' => $otherFarm->id,
            'status' => 'active',
            'acquisition_type' => 'born',
        ])->assertSessionHasErrors('farm_id');

        $this->assertDatabaseMissing('animals', ['ear_tag' => 'FORBIDDEN-001']);
    }

    public function test_administrator_cannot_assign_a_user_to_an_unassigned_farm(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $farm = Farm::factory()->create();
        $otherFarm = Farm::factory()->create();
        $administrator = User::factory()->create();
        $administrator->assignRole(Role::findByName(AccessControl::ADMIN_ROLE, 'web'));
        $administrator->farms()->attach($farm);
        $operatorRole = Role::findByName(AccessControl::OPERATOR_ROLE, 'web');
        $this->actingAs($administrator);

        $this->post(route('admin.users.store'), [
            'name' => 'Usuario de otra finca',
            'email' => 'other-farm-user@example.test',
            'password' => 'ValidPassword123',
            'password_confirmation' => 'ValidPassword123',
            'roles' => [$operatorRole->id],
            'farm_ids' => [$otherFarm->id],
        ])->assertSessionHasErrors('farm_ids.0');

        $this->assertDatabaseMissing('users', ['email' => 'other-farm-user@example.test']);
    }

    public function test_dashboard_and_inventory_report_only_include_assigned_farms(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $farm = Farm::factory()->create();
        $otherFarm = Farm::factory()->create();
        Animal::factory()->create(['farm_id' => $farm->id, 'ear_tag' => 'VISIBLE-001']);
        Animal::factory()->create(['farm_id' => $otherFarm->id, 'ear_tag' => 'HIDDEN-001']);
        $user = User::factory()->create();
        $user->assignRole(Role::findByName(AccessControl::OPERATOR_ROLE, 'web'));
        $user->farms()->attach($farm);
        $this->actingAs($user);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee($farm->name)
            ->assertDontSee($otherFarm->name);

        $this->get(route('reports.index', ['report' => 'inventory']))
            ->assertOk()
            ->assertSee('VISIBLE-001')
            ->assertDontSee('HIDDEN-001');
    }
}
