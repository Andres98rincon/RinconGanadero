<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\AnimalEvent;
use App\Models\Farm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WeightControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_bovine_tape_estimate_returns_a_rounded_reference_without_creating_a_weighing(): void
    {
        $this->get(route('weights.estimate'))
            ->assertOk()
            ->assertSee('Estimador de peso bovino');

        $response = $this->post(route('weights.estimate.calculate'), [
            'heart_girth_cm' => 180,
            'body_length_cm' => 150,
        ]);

        $response->assertOk()
            ->assertSee('448 kg')
            ->assertSee('no se guarda en el historial');
        $this->assertDatabaseCount('animal_events', 0);
    }

    public function test_bovine_tape_estimate_accepts_metric_values_with_decimal_commas(): void
    {
        $response = $this->post(route('weights.estimate.calculate'), [
            'heart_girth_cm' => '1,80',
            'body_length_cm' => '1,50',
        ]);

        $response->assertOk()
            ->assertSee('448 kg')
            ->assertSee('180,0 cm')
            ->assertSee('150,0 cm');
        $this->assertDatabaseCount('animal_events', 0);
    }

    public function test_bovine_tape_estimate_rejects_measurements_outside_supported_range(): void
    {
        $response = $this->from(route('weights.estimate'))
            ->post(route('weights.estimate.calculate'), [
                'heart_girth_cm' => 20,
                'body_length_cm' => 150,
            ]);

        $response->assertRedirect(route('weights.estimate'))
            ->assertSessionHasErrors([
                'heart_girth_cm' => 'El perímetro torácico debe ser de al menos 50 cm.',
            ]);
        $this->assertDatabaseCount('animal_events', 0);
    }

    public function test_user_without_weight_view_permission_cannot_use_the_estimator(): void
    {
        $role = Role::create([
            'name' => 'lector_sin_pesajes',
            'guard_name' => 'web',
        ]);
        $operator = User::factory()->create();
        $operator->assignRole($role);

        $this->actingAs($operator)
            ->get(route('weights.estimate'))
            ->assertForbidden();
    }

    public function test_dashboard_renders_an_empty_state_without_weight_records(): void
    {
        $response = $this->get(route('weights.index'));

        $response->assertOk()
            ->assertSee('Sin pesajes para los filtros seleccionados.')
            ->assertSee('0,00');
    }

    public function test_dashboard_filters_weighings_and_calculates_growth_from_previous_weight(): void
    {
        $farm = Farm::factory()->create(['name' => 'Finca Crecimiento']);
        $animal = Animal::factory()->create([
            'farm_id' => $farm->id,
            'ear_tag' => 'PESO-101',
            'birth_date' => '2025-01-01',
            'birth_weight_kg' => 30,
        ]);
        $animal->events()->create([
            'event_type' => 'weighing',
            'event_date' => '2025-01-10',
            'title' => 'Pesaje inicial',
            'weight_kg' => 50,
        ]);
        $animal->events()->create([
            'event_type' => 'weighing',
            'event_date' => '2025-02-09',
            'title' => 'Pesaje de seguimiento',
            'weight_kg' => 70,
        ]);
        $otherAnimal = Animal::factory()->create(['ear_tag' => 'PESO-202']);
        $otherAnimal->events()->create([
            'event_type' => 'weighing',
            'event_date' => '2025-02-09',
            'title' => 'Pesaje de otra finca',
            'weight_kg' => 300,
        ]);

        $response = $this->get(route('weights.index', [
            'from' => '2025-01-10',
            'to' => '2025-02-09',
            'farm_id' => $farm->id,
            'q' => 'PESO-101',
        ]));

        $response->assertOk()
            ->assertSee('PESO-101')
            ->assertSee('Finca Crecimiento')
            ->assertSee('30,00 kg')
            ->assertSee('20,00 kg')
            ->assertSee('0,667 kg/día')
            ->assertDontSee('PESO-202');
    }

    public function test_recording_a_weight_updates_the_animal_summary_and_history(): void
    {
        $this->freezeTime();
        $animal = Animal::factory()->create([
            'ear_tag' => 'PESO-303',
            'birth_date' => '2025-01-01',
        ]);
        $eventDate = today()->subDay()->toDateString();

        $response = $this->post(route('weights.store'), [
            'animal_id' => $animal->id,
            'event_date' => $eventDate,
            'weight_kg' => 248.75,
            'description' => 'Control mensual',
        ]);

        $event = AnimalEvent::query()->whereBelongsTo($animal)->firstOrFail();
        $response->assertRedirect(route('weights.index', [
            'from' => $eventDate,
            'to' => $eventDate,
            'animal_id' => $animal->id,
        ]));
        $this->assertDatabaseHas('animal_events', [
            'id' => $event->id,
            'animal_id' => $animal->id,
            'event_type' => 'weighing',
            'weight_kg' => 248.75,
            'description' => 'Control mensual',
        ]);
        $this->assertSame('248.75', $animal->fresh()->current_weight_kg);
        $this->assertSame($eventDate, $animal->fresh()->last_weighed_at->toDateString());
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => AnimalEvent::class,
            'subject_id' => $event->id,
            'event' => 'created',
        ]);
        $this->assertDatabaseMissing('activity_log', [
            'subject_type' => Animal::class,
            'subject_id' => $animal->id,
            'event' => 'updated',
        ]);
    }

    public function test_recording_an_older_weight_does_not_replace_the_latest_weight(): void
    {
        $this->freezeTime();
        $animal = Animal::factory()->create([
            'current_weight_kg' => 400,
            'last_weighed_at' => '2025-06-01',
            'birth_date' => '2024-01-01',
        ]);
        $animal->events()->create([
            'event_type' => 'weighing',
            'event_date' => '2025-06-01',
            'title' => 'Pesaje reciente',
            'weight_kg' => 400,
        ]);

        $this->post(route('weights.store'), [
            'animal_id' => $animal->id,
            'event_date' => '2025-05-01',
            'weight_kg' => 380,
        ])->assertRedirect();

        $this->assertSame('400.00', $animal->fresh()->current_weight_kg);
        $this->assertSame('2025-06-01', $animal->fresh()->last_weighed_at->toDateString());
        $this->assertSame(2, AnimalEvent::query()->whereBelongsTo($animal)->count());
    }

    public function test_weight_form_rejects_an_inactive_animal(): void
    {
        $this->freezeTime();
        $animal = Animal::factory()->create(['status' => 'sold']);

        $response = $this->post(route('weights.store'), [
            'animal_id' => $animal->id,
            'event_date' => today()->subDay()->toDateString(),
            'weight_kg' => 200,
        ]);

        $response->assertSessionHasErrors([
            'animal_id' => 'Selecciona un animal activo del inventario.',
        ]);
        $this->assertDatabaseCount('animal_events', 0);
    }

    public function test_weight_form_rejects_a_measurement_before_the_animal_was_born(): void
    {
        $animal = Animal::factory()->create(['birth_date' => '2025-04-10']);

        $response = $this->post(route('weights.store'), [
            'animal_id' => $animal->id,
            'event_date' => '2025-04-09',
            'weight_kg' => 35,
        ]);

        $response->assertSessionHasErrors([
            'event_date' => 'La fecha del pesaje no puede ser anterior al nacimiento.',
        ]);
        $this->assertDatabaseCount('animal_events', 0);
    }

    public function test_weight_form_requires_an_animal_date_and_measured_weight(): void
    {
        $response = $this->post(route('weights.store'), []);

        $response->assertSessionHasErrors(['animal_id', 'event_date', 'weight_kg']);
        $this->assertDatabaseCount('animal_events', 0);
    }

    public function test_weight_form_rejects_future_dates_and_zero_weight(): void
    {
        $this->freezeTime();
        $animal = Animal::factory()->create(['birth_date' => '2024-01-01']);

        $response = $this->post(route('weights.store'), [
            'animal_id' => $animal->id,
            'event_date' => today()->addDay()->toDateString(),
            'weight_kg' => 0,
        ]);

        $response->assertSessionHasErrors([
            'event_date' => 'La fecha del pesaje no puede ser futura.',
            'weight_kg' => 'El peso debe ser mayor que cero.',
        ]);
        $this->assertDatabaseCount('animal_events', 0);
    }

    public function test_weight_form_search_only_returns_matching_active_animals(): void
    {
        Animal::factory()->create(['ear_tag' => 'BUSCA-101', 'status' => 'active']);
        Animal::factory()->create(['ear_tag' => 'OTRO-202', 'status' => 'active']);
        Animal::factory()->create(['ear_tag' => 'BUSCA-303', 'status' => 'sold']);

        $response = $this->get(route('weights.create', ['q' => 'BUSCA']));

        $response->assertOk()
            ->assertSee('BUSCA-101')
            ->assertDontSee('OTRO-202')
            ->assertDontSee('BUSCA-303');
    }

    public function test_dashboard_rejects_a_period_whose_end_precedes_its_start(): void
    {
        $response = $this->get(route('weights.index', [
            'from' => '2025-02-10',
            'to' => '2025-02-09',
        ]));

        $response->assertSessionHasErrors([
            'to' => 'La fecha final debe ser igual o posterior a la inicial.',
        ]);
    }

    public function test_dashboard_search_does_not_treat_input_as_sql(): void
    {
        $animal = Animal::factory()->create(['ear_tag' => 'SEGURO-101']);
        $animal->events()->create([
            'event_type' => 'weighing',
            'event_date' => today()->subDay()->toDateString(),
            'title' => 'Pesaje de control',
            'weight_kg' => 250,
        ]);

        $response = $this->get(route('weights.index', ['q' => "x%' OR 1=1 --"]));

        $response->assertOk()
            ->assertSee('Sin pesajes para los filtros seleccionados.')
            ->assertDontSee('SEGURO-101');
    }

    public function test_weight_dashboard_escapes_observation_text(): void
    {
        $animal = Animal::factory()->create();
        $animal->events()->create([
            'event_type' => 'weighing',
            'event_date' => today()->subDay()->toDateString(),
            'title' => 'Pesaje de control',
            'weight_kg' => 250,
            'description' => '<script>alert(1)</script>',
        ]);

        $response = $this->get(route('weights.index'));

        $response->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }
}
