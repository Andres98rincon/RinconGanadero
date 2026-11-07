<?php

namespace Tests\Feature;

use App\AccessControl;
use App\Models\Animal;
use App\Models\Farm;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OfflineSyncControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_offline_operation_is_synced_once_when_retried(): void
    {
        $farm = Farm::factory()->create();
        $animal = Animal::factory()->create(['farm_id' => $farm->id]);
        $offlineId = (string) Str::uuid();
        $operation = ['id' => $offlineId, 'type' => 'animal_event', 'animal_id' => $animal->id, 'payload' => ['event_type' => 'vaccination', 'event_date' => today()->toDateString(), 'title' => 'Vacuna offline']];

        $this->postJson(route('offline.sync'), ['operations' => [$operation]])->assertOk()->assertJsonPath('results.0.status', 'synced');
        $this->postJson(route('offline.sync'), ['operations' => [$operation]])->assertOk()->assertJsonPath('results.0.duplicate', true);

        $this->assertDatabaseCount('animal_events', 1);
        $this->assertDatabaseHas('animal_events', ['offline_id' => $offlineId, 'title' => 'Vacuna offline']);
    }

    public function test_offline_operation_cannot_be_synced_to_an_inaccessible_farm(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $farm = Farm::factory()->create();
        $animal = Animal::factory()->create(['farm_id' => $farm->id]);
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate(AccessControl::OPERATOR_ROLE, 'web'));
        $this->actingAs($user);

        $this->postJson(route('offline.sync'), ['operations' => [[
            'id' => (string) Str::uuid(), 'type' => 'animal_event', 'animal_id' => $animal->id,
            'payload' => ['event_type' => 'treatment', 'event_date' => today()->toDateString(), 'title' => 'No autorizado'],
        ]]])->assertOk()->assertJsonPath('results.0.status', 'denied');
        $this->assertDatabaseCount('animal_events', 0);
    }
}
