<?php

namespace Tests\Feature;

use App\AccessControl;
use App\Models\Animal;
use App\Models\Farm;
use App\Models\MilkProductionRecord;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HerdAssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_assistant_counts_only_animals_from_assigned_farms(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $farm = Farm::factory()->create(['name' => 'La Esperanza']);
        $otherFarm = Farm::factory()->create();
        Animal::factory()->count(3)->create(['farm_id' => $farm->id, 'sex' => 'female']);
        Animal::factory()->count(5)->create(['farm_id' => $otherFarm->id]);
        $operator = User::factory()->create();
        $operator->assignRole(Role::findByName(AccessControl::OPERATOR_ROLE, 'web'));
        $operator->farms()->attach($farm);
        $this->actingAs($operator);

        $this->postJson(route('assistant.ask'), ['message' => '¿Cuántas vacas tengo en La Esperanza?'])
            ->assertOk()
            ->assertJsonPath('intent', 'animals')
            ->assertJsonPath('answer', 'Tienes 3 vacas y hembras bovinas en La Esperanza.');
    }

    public function test_assistant_reports_milk_produced_this_week(): void
    {
        $cow = Animal::factory()->create(['farm_id' => Farm::factory()->create()->id, 'sex' => 'female']);
        MilkProductionRecord::factory()->create(['animal_id' => $cow->id, 'record_date' => today()->toDateString(), 'morning_liters' => 10, 'evening_liters' => 5.5]);

        $this->postJson(route('assistant.ask'), ['message' => 'cuanta leche se produjo esta semana'])
            ->assertOk()
            ->assertJsonPath('intent', 'milk')
            ->assertJsonPath('answer', 'Se produjeron 15,5 L en total esta semana.');
    }

    public function test_assistant_opens_an_animal_summary_by_ear_tag(): void
    {
        $animal = Animal::factory()->create(['farm_id' => Farm::factory()->create()->id, 'ear_tag' => 'HT-0123', 'name' => 'Lucero']);

        $this->postJson(route('assistant.ask'), ['message' => 'muéstrame la ficha de ht-0123'])
            ->assertOk()
            ->assertJsonPath('intent', 'animal_info')
            ->assertJsonPath('link.url', route('animals.show', $animal));
    }

    public function test_assistant_respects_module_permissions(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->givePermissionTo('animals.view');
        $this->actingAs($user);

        $this->postJson(route('assistant.ask'), ['message' => '¿Cuánto gasté este mes?'])
            ->assertOk()
            ->assertJsonPath('intent', 'finance')
            ->assertJsonPath('items', []);
        $this->assertStringContainsString('no tiene permiso', $this->postJson(route('assistant.ask'), ['message' => 'balance del año'])->json('answer'));
    }

    public function test_assistant_validates_input_and_requires_authentication(): void
    {
        $this->postJson(route('assistant.ask'), ['message' => str_repeat('a', 301)])->assertUnprocessable();

        $this->logOutTestUser();
        $this->postJson(route('assistant.ask'), ['message' => 'hola'])->assertUnauthorized();
    }
}
