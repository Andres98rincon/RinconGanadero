<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\AnimalEvent;
use App\Models\CalendarReminder;
use App\Models\Farm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnimalQrAndCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_animal_qr_image_is_generated_and_resolves_to_the_animal(): void
    {
        $animal = Animal::factory()->create(['qr_code' => 'ABC123XYZ7890001']);

        $this->get(route('animals.qr.image', $animal))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml; charset=UTF-8')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertSee('<svg', false);

        $this->get(route('animals.qr.resolve', $animal->qr_code))
            ->assertRedirect(route('animals.show', $animal));

        $this->get(route('animals.lookup', ['code' => strtolower($animal->qr_code)]))
            ->assertRedirect(route('animals.show', $animal));
    }

    public function test_calendar_displays_scheduled_animal_events_and_accepts_reminders(): void
    {
        $farm = Farm::factory()->create(['name' => 'La Esperanza']);
        $animal = Animal::factory()->create(['farm_id' => $farm->id, 'ear_tag' => 'CAL-001']);
        $animal->events()->create([
            'event_type' => 'vaccination',
            'event_date' => today()->subDay()->toDateString(),
            'due_date' => today()->addDays(3)->toDateString(),
            'title' => 'Refuerzo aftosa',
        ]);

        $this->get(route('calendar.index', ['view' => 'month', 'date' => today()->toDateString()]))
            ->assertOk()
            ->assertSee('Refuerzo aftosa')
            ->assertSee('La Esperanza');

        $this->post(route('calendar.reminders.store'), [
            'farm_id' => $farm->id,
            'animal_id' => $animal->id,
            'title' => 'Revisar potrero',
            'description' => 'Comprobar bebederos',
            'event_date' => today()->addDays(4)->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseHas('calendar_reminders', [
            'farm_id' => $farm->id,
            'animal_id' => $animal->id,
            'title' => 'Revisar potrero',
        ]);
    }
}
