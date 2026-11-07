<?php

namespace Tests\Unit;

use App\Models\Animal;
use App\Models\NotificationPreference;
use App\Models\Farm;
use App\Services\LivestockNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LivestockNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_an_upcoming_event_once_and_persists_the_farm_context(): void
    {
        $this->freezeTime();
        $user = auth()->user();
        $farm = Farm::factory()->create(['name' => 'Finca de prueba']);
        $user->farms()->attach($farm);
        $animal = Animal::factory()->create(['farm_id' => $farm->id, 'ear_tag' => 'NOT-001']);
        $animal->events()->create([
            'event_type' => 'vaccination',
            'event_date' => today()->toDateString(),
            'due_date' => today()->addDay()->toDateString(),
            'title' => 'Vacuna de prueba',
        ]);
        NotificationPreference::query()->create(['user_id' => $user->id, 'enabled' => true, 'lead_days' => 3]);

        $service = app(LivestockNotificationService::class);

        $this->assertSame(1, $service->dispatchUpcoming());
        $this->assertSame(0, $service->dispatchUpcoming());
        $this->assertDatabaseHas('notification_deliveries', ['user_id' => $user->id, 'farm_id' => $farm->id]);
        $this->assertCount(1, $user->fresh()->notifications);
        $this->assertSame('Vacuna de prueba', $user->fresh()->notifications->first()->data['title']);
    }

    public function test_disabled_preferences_prevent_dispatch(): void
    {
        $user = auth()->user();
        NotificationPreference::query()->create(['user_id' => $user->id, 'enabled' => false, 'lead_days' => 7]);

        $this->assertSame(0, app(LivestockNotificationService::class)->dispatchUpcoming());
        $this->assertDatabaseCount('notification_deliveries', 0);
    }
}
