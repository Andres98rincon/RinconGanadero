<?php

namespace Tests\Feature;

use App\AccessControl;
use App\Models\Animal;
use App\Models\Farm;
use App\Models\MilkProductionRecord;
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

    public function test_offline_weight_is_synced_once_and_updates_current_weight(): void
    {
        $animal = Animal::factory()->create(['farm_id' => Farm::factory()->create()->id, 'birth_date' => today()->subYears(2)->toDateString()]);
        $operation = ['id' => (string) Str::uuid(), 'type' => 'weight', 'animal_id' => $animal->id, 'payload' => ['event_date' => today()->toDateString(), 'weight_kg' => '412.5', 'description' => '']];

        $this->postJson(route('offline.sync'), ['operations' => [$operation]])->assertOk()->assertJsonPath('results.0.status', 'synced');
        $this->postJson(route('offline.sync'), ['operations' => [$operation]])->assertOk()->assertJsonPath('results.0.duplicate', true);

        $this->assertDatabaseCount('animal_events', 1);
        $this->assertDatabaseHas('animal_events', ['offline_id' => $operation['id'], 'event_type' => 'weighing', 'weight_kg' => 412.5]);
        $this->assertEquals(412.5, (float) $animal->fresh()->current_weight_kg);
    }

    public function test_offline_milk_production_is_synced_once_and_reports_date_conflicts(): void
    {
        $cow = Animal::factory()->create(['farm_id' => Farm::factory()->create()->id, 'sex' => 'female']);
        $date = today()->subDay()->toDateString();
        $operation = ['id' => (string) Str::uuid(), 'type' => 'milk_production', 'animal_id' => $cow->id, 'payload' => ['record_date' => $date, 'morning_liters' => '10.5', 'evening_liters' => '7']];
        $sameDayFromAnotherDevice = ['id' => (string) Str::uuid(), 'type' => 'milk_production', 'animal_id' => $cow->id, 'payload' => ['record_date' => $date, 'morning_liters' => '4']];

        $this->postJson(route('offline.sync'), ['operations' => [$operation, $sameDayFromAnotherDevice]])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'synced')
            ->assertJsonPath('results.1.status', 'conflict');
        $this->postJson(route('offline.sync'), ['operations' => [$operation]])->assertOk()->assertJsonPath('results.0.duplicate', true);

        $this->assertSame(1, MilkProductionRecord::query()->count());
        $this->assertDatabaseHas('milk_production_records', ['offline_id' => $operation['id'], 'total_liters' => 17.5]);
    }

    public function test_invalid_offline_operation_is_reported_for_review_without_saving(): void
    {
        $cow = Animal::factory()->create(['farm_id' => Farm::factory()->create()->id, 'sex' => 'female']);

        $this->postJson(route('offline.sync'), ['operations' => [[
            'id' => (string) Str::uuid(), 'type' => 'milk_production', 'animal_id' => $cow->id,
            'payload' => ['record_date' => today()->toDateString(), 'morning_liters' => '', 'evening_liters' => ''],
        ]]])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'invalid')
            ->assertJsonPath('results.0.message', 'Registra al menos un ordeño para guardar la producción.');

        $this->assertSame(0, MilkProductionRecord::query()->count());
    }

    public function test_status_endpoint_returns_session_and_fresh_csrf_token(): void
    {
        $response = $this->getJson(route('offline.status'))
            ->assertOk()
            ->assertJsonPath('online', true)
            ->assertJsonPath('user_id', auth()->id())
            ->assertJsonStructure(['csrf_token', 'server_time']);

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_status_endpoint_rejects_expired_sessions(): void
    {
        $this->logOutTestUser();

        $this->getJson(route('offline.status'))->assertUnauthorized();
    }

    public function test_online_event_submission_with_known_offline_id_is_not_duplicated(): void
    {
        $animal = Animal::factory()->create(['farm_id' => Farm::factory()->create()->id]);
        $payload = ['offline_id' => (string) Str::uuid(), 'event_type' => 'deworming', 'event_date' => today()->toDateString(), 'title' => 'Desparasitación'];

        $this->post(route('animals.events.store', $animal), $payload)->assertRedirect(route('animals.show', $animal));
        $this->post(route('animals.events.store', $animal), $payload)->assertRedirect(route('animals.show', $animal));

        $this->assertDatabaseCount('animal_events', 1);
    }
}
