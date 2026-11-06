<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\ReproductiveEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReproductiveEventRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_reproductive_dashboard_lists_events_and_pregnancy_summary(): void
    {
        $cow = Animal::factory()->create([
            'sex' => 'female',
            'reproductive_status' => 'pregnant',
            'expected_calving_date' => today()->addDays(18),
            'ear_tag' => 'VACA-101',
        ]);
        $cow->reproductiveEvents()->create([
            'event_type' => 'pregnancy_diagnosis',
            'event_date' => today()->subDays(30),
            'diagnosis_result' => 'pregnant',
            'expected_calving_date' => today()->addDays(18),
        ]);

        $response = $this->get(route('reproduction.index'));

        $response->assertOk()
            ->assertSee('Vacas registradas')
            ->assertSee('VACA-101')
            ->assertSee('Próximos partos');
    }

    public function test_new_reproductive_event_form_lists_active_cows_and_event_types(): void
    {
        Animal::factory()->create(['sex' => 'female', 'ear_tag' => 'VACA-102']);
        Animal::factory()->create(['sex' => 'male', 'ear_tag' => 'TORO-102']);

        $response = $this->get(route('reproduction.create'));

        $response->assertOk()
            ->assertSee('VACA-102')
            ->assertSee('Diagnóstico de preñez')
            ->assertSee('Registrar evento reproductivo');
    }

    public function test_heat_registration_updates_the_cow_reproductive_status(): void
    {
        $cow = Animal::factory()->create(['sex' => 'female', 'reproductive_status' => null]);

        $response = $this->post(route('reproduction.store'), $this->validEventData($cow, [
            'event_type' => 'heat',
            'sire_animal_id' => '',
            'sire_name' => '',
            'semen_code' => '',
            'technician' => '',
            'diagnosis_result' => '',
            'expected_calving_date' => '',
            'gestation_weeks' => '',
            'calving_difficulty' => '',
            'calves' => [
                ['ear_tag' => '', 'name' => '', 'sex' => '', 'birth_weight_kg' => ''],
            ],
        ]));

        $event = ReproductiveEvent::query()->firstOrFail();

        $response->assertRedirect(route('reproduction.show', $event));
        $this->assertDatabaseHas('reproductive_events', [
            'id' => $event->id,
            'animal_id' => $cow->id,
            'event_type' => 'heat',
        ]);
        $this->assertSame('open', $cow->fresh()->reproductive_status);
    }

    public function test_natural_mating_registers_the_bull_and_marks_the_cow_served(): void
    {
        $cow = Animal::factory()->create(['sex' => 'female']);
        $bull = Animal::factory()->create(['sex' => 'male', 'ear_tag' => 'TORO-103']);

        $response = $this->post(route('reproduction.store'), $this->validEventData($cow, [
            'event_type' => 'natural_mating',
            'sire_animal_id' => $bull->id,
        ]));

        $response->assertRedirect();
        $this->assertDatabaseHas('reproductive_events', [
            'animal_id' => $cow->id,
            'event_type' => 'natural_mating',
            'sire_animal_id' => $bull->id,
        ]);
        $this->assertSame('served', $cow->fresh()->reproductive_status);
    }

    public function test_artificial_insemination_records_the_dose_and_technician(): void
    {
        $cow = Animal::factory()->create(['sex' => 'female']);

        $response = $this->post(route('reproduction.store'), $this->validEventData($cow, [
            'event_type' => 'artificial_insemination',
            'sire_name' => 'Semental externo 24',
            'semen_code' => 'PAJ-IA-900',
            'technician' => 'Técnica Ana Ruiz',
        ]));

        $response->assertRedirect();
        $this->assertDatabaseHas('reproductive_events', [
            'animal_id' => $cow->id,
            'event_type' => 'artificial_insemination',
            'sire_name' => 'Semental externo 24',
            'semen_code' => 'PAJ-IA-900',
            'technician' => 'Técnica Ana Ruiz',
        ]);
        $this->assertSame('served', $cow->fresh()->reproductive_status);
    }

    public function test_service_without_a_sire_is_rejected(): void
    {
        $cow = Animal::factory()->create(['sex' => 'female']);

        $response = $this->post(route('reproduction.store'), $this->validEventData($cow, [
            'event_type' => 'artificial_insemination',
        ]));

        $response->assertSessionHasErrors('sire_animal_id');
        $this->assertDatabaseCount('reproductive_events', 0);
    }

    public function test_pregnancy_diagnosis_saves_the_probable_calving_date(): void
    {
        $cow = Animal::factory()->create(['sex' => 'female']);
        $expectedDate = today()->addDays(280)->toDateString();

        $response = $this->post(route('reproduction.store'), $this->validEventData($cow, [
            'event_type' => 'pregnancy_diagnosis',
            'diagnosis_result' => 'pregnant',
            'expected_calving_date' => $expectedDate,
        ]));
        $event = ReproductiveEvent::query()->firstOrFail();

        $response->assertRedirect();
        $this->assertDatabaseHas('reproductive_events', [
            'animal_id' => $cow->id,
            'diagnosis_result' => 'pregnant',
        ]);
        $this->assertSame($expectedDate, $event->fresh()->expected_calving_date->toDateString());
        $this->assertSame('pregnant', $cow->fresh()->reproductive_status);
        $this->assertSame($expectedDate, $cow->fresh()->expected_calving_date->toDateString());
    }

    public function test_positive_pregnancy_diagnosis_requires_a_probable_calving_date(): void
    {
        $cow = Animal::factory()->create(['sex' => 'female']);

        $response = $this->post(route('reproduction.store'), $this->validEventData($cow, [
            'event_type' => 'pregnancy_diagnosis',
            'diagnosis_result' => 'pregnant',
        ]));

        $response->assertSessionHasErrors('expected_calving_date');
        $this->assertDatabaseCount('reproductive_events', 0);
        $this->assertNull($cow->fresh()->reproductive_status);
    }

    public function test_abortion_registration_reopens_the_cow_for_reproduction(): void
    {
        $cow = Animal::factory()->create([
            'sex' => 'female',
            'reproductive_status' => 'pregnant',
            'expected_calving_date' => today()->addDays(100),
        ]);

        $response = $this->post(route('reproduction.store'), $this->validEventData($cow, [
            'event_type' => 'abortion',
            'gestation_weeks' => 12,
        ]));

        $response->assertRedirect();
        $this->assertDatabaseHas('reproductive_events', [
            'animal_id' => $cow->id,
            'event_type' => 'abortion',
            'gestation_weeks' => 12,
        ]);
        $this->assertSame('open', $cow->fresh()->reproductive_status);
        $this->assertNull($cow->fresh()->expected_calving_date);
    }

    public function test_backdated_events_do_not_overwrite_a_more_recent_reproductive_status(): void
    {
        $cow = Animal::factory()->create(['sex' => 'female']);
        $expectedDate = today()->addDays(250);
        $cow->reproductiveEvents()->create([
            'event_type' => 'pregnancy_diagnosis',
            'event_date' => today()->subDays(2),
            'diagnosis_result' => 'pregnant',
            'expected_calving_date' => $expectedDate,
        ]);
        $cow->syncReproductiveStatus();

        $response = $this->post(route('reproduction.store'), $this->validEventData($cow, [
            'event_type' => 'heat',
            'event_date' => today()->subDays(10)->toDateString(),
        ]));

        $response->assertRedirect();
        $this->assertSame('pregnant', $cow->fresh()->reproductive_status);
        $this->assertSame($expectedDate->toDateString(), $cow->fresh()->expected_calving_date->toDateString());
    }

    public function test_editing_a_cow_record_cannot_override_its_event_derived_reproductive_status(): void
    {
        $cow = Animal::factory()->create(['sex' => 'female']);
        $expectedDate = today()->addDays(250);
        $cow->reproductiveEvents()->create([
            'event_type' => 'pregnancy_diagnosis',
            'event_date' => today()->subDays(5),
            'diagnosis_result' => 'pregnant',
            'expected_calving_date' => $expectedDate,
        ]);
        $cow->syncReproductiveStatus();

        $response = $this->put(route('animals.update', $cow), [
            'ear_tag' => $cow->ear_tag,
            'species' => 'bovine',
            'sex' => 'female',
            'identification_method' => 'visual',
            'status' => 'active',
            'acquisition_type' => 'born',
            'reproductive_status' => 'open',
        ]);

        $response->assertRedirect(route('animals.show', $cow));
        $this->assertSame('pregnant', $cow->fresh()->reproductive_status);
        $this->assertSame($expectedDate->toDateString(), $cow->fresh()->expected_calving_date->toDateString());
    }

    public function test_calving_creates_and_links_multiple_calf_records_atomically(): void
    {
        $cow = Animal::factory()->create([
            'sex' => 'female',
            'reproductive_status' => 'pregnant',
            'expected_calving_date' => today(),
        ]);
        $bull = Animal::factory()->create(['sex' => 'male']);

        $response = $this->post(route('reproduction.store'), $this->validEventData($cow, [
            'event_type' => 'calving',
            'sire_animal_id' => $bull->id,
            'calving_difficulty' => 'normal',
            'calves' => [
                ['ear_tag' => 'TERNERO-101', 'name' => 'Luna', 'sex' => 'female', 'birth_weight_kg' => 32.5],
                ['ear_tag' => 'TERNERO-102', 'sex' => 'male', 'birth_weight_kg' => 34],
            ],
        ]));

        $event = ReproductiveEvent::query()->firstOrFail();
        $calves = $event->calves()->orderBy('animals.ear_tag')->get();

        $response->assertRedirect(route('reproduction.show', $event));
        $this->assertDatabaseHas('reproductive_events', [
            'id' => $event->id,
            'event_type' => 'calving',
            'calving_difficulty' => 'normal',
        ]);
        $this->assertDatabaseHas('animals', [
            'ear_tag' => 'TERNERO-101',
            'dam_id' => $cow->id,
            'sire_id' => $bull->id,
            'sex' => 'female',
            'birth_weight_kg' => 32.5,
        ]);
        $this->assertSame(2, $calves->count());
        $this->assertSame('lactating', $cow->fresh()->reproductive_status);
        $this->assertNull($cow->fresh()->expected_calving_date);
    }

    public function test_invalid_calving_data_does_not_create_the_event_or_any_calves(): void
    {
        $cow = Animal::factory()->create(['sex' => 'female']);

        $response = $this->post(route('reproduction.store'), $this->validEventData($cow, [
            'event_type' => 'calving',
            'calving_difficulty' => 'normal',
            'calves' => [
                ['ear_tag' => 'TERNERO-103', 'sex' => 'female'],
                ['ear_tag' => 'TERNERO-103', 'sex' => 'male'],
            ],
        ]));

        $response->assertSessionHasErrors('calves.1.ear_tag');
        $this->assertDatabaseCount('reproductive_events', 0);
        $this->assertDatabaseMissing('animals', ['ear_tag' => 'TERNERO-103']);
    }

    public function test_reproductive_events_only_accept_active_female_bovines(): void
    {
        $male = Animal::factory()->create(['sex' => 'male']);

        $response = $this->post(route('reproduction.store'), $this->validEventData($male, [
            'event_type' => 'heat',
        ]));

        $response->assertSessionHasErrors('animal_id');
        $this->assertDatabaseCount('reproductive_events', 0);
    }

    public function test_reproductive_history_is_available_from_the_cow_record(): void
    {
        $cow = Animal::factory()->create(['sex' => 'female']);
        $cow->reproductiveEvents()->create([
            'event_type' => 'heat',
            'event_date' => today()->subDay(),
        ]);

        $response = $this->get(route('animals.show', $cow));

        $response->assertOk()
            ->assertSee('Historial reproductivo y productivo')
            ->assertSee('Ver producción')
            ->assertSee('Celo');
    }

    public function test_reproductive_event_detail_escapes_observation_text(): void
    {
        $cow = Animal::factory()->create(['sex' => 'female']);
        $event = $cow->reproductiveEvents()->create([
            'event_type' => 'heat',
            'event_date' => today()->subDay(),
            'notes' => '<script>alert(1)</script>',
        ]);

        $response = $this->get(route('reproduction.show', $event));

        $response->assertOk()
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    private function validEventData(Animal $cow, array $overrides = []): array
    {
        return array_merge([
            'animal_id' => $cow->id,
            'event_type' => 'heat',
            'event_date' => today()->subDay()->toDateString(),
        ], $overrides);
    }
}
