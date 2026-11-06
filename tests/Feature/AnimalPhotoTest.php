<?php

namespace Tests\Feature;

use App\Models\Animal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AnimalPhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_animal_photo_can_be_uploaded_and_displayed_on_its_detail_page(): void
    {
        Storage::fake('public');

        $response = $this->post(route('animals.store'), $this->validAnimalData([
            'photo' => UploadedFile::fake()->image('lucera.png'),
        ]));

        $animal = Animal::query()->where('ear_tag', 'PHOTO-101')->firstOrFail();
        $media = $animal->getFirstMedia('photo');

        $response->assertRedirect(route('animals.show', $animal));
        $this->assertNotNull($media);
        $this->assertSame('lucera.png', $media->file_name);
        Storage::disk('public')->assertExists($media->getPathRelativeToRoot());
        $this->assertTrue($media->hasGeneratedConversion('optimized'));
        Storage::disk('public')->assertExists($media->getPathRelativeToRoot('optimized'));

        $this->get(route('animals.show', $animal))
            ->assertOk()
            ->assertSee('Foto de PHOTO-101');

        $this->get(route('animals.index'))
            ->assertOk()
            ->assertSee('Foto de PHOTO-101');
    }

    public function test_uploading_a_new_photo_replaces_the_existing_photo(): void
    {
        Storage::fake('public');

        $animal = $this->createAnimalWithPhoto('anterior.png');
        $response = $this->put(route('animals.update', $animal), $this->validAnimalData([
            'ear_tag' => $animal->ear_tag,
            'photo' => UploadedFile::fake()->image('actualizada.png'),
        ]));

        $photos = $animal->fresh()->getMedia('photo');

        $response->assertRedirect(route('animals.show', $animal));
        $this->assertCount(1, $photos);
        $this->assertSame('actualizada.png', $photos->first()->file_name);
    }

    public function test_non_image_files_are_rejected_without_creating_an_animal(): void
    {
        Storage::fake('public');

        $response = $this->post(route('animals.store'), $this->validAnimalData([
            'photo' => UploadedFile::fake()->create('animal.svg', 20, 'image/svg+xml'),
        ]));

        $response->assertSessionHasErrors('photo');
        $this->assertDatabaseMissing('animals', ['ear_tag' => 'PHOTO-101']);
    }

    public function test_current_photo_can_be_removed_from_animal_record(): void
    {
        Storage::fake('public');

        $animal = $this->createAnimalWithPhoto('para-eliminar.png');
        $response = $this->put(route('animals.update', $animal), $this->validAnimalData([
            'ear_tag' => $animal->ear_tag,
            'remove_photo' => '1',
        ]));

        $response->assertRedirect(route('animals.show', $animal));
        $this->assertCount(0, $animal->fresh()->getMedia('photo'));
    }

    private function createAnimalWithPhoto(string $fileName): Animal
    {
        $this->post(route('animals.store'), $this->validAnimalData([
            'photo' => UploadedFile::fake()->image($fileName),
        ]))->assertRedirect();

        return Animal::query()->where('ear_tag', 'PHOTO-101')->firstOrFail();
    }

    private function validAnimalData(array $overrides = []): array
    {
        return array_merge([
            'ear_tag' => 'PHOTO-101',
            'species' => 'bovine',
            'sex' => 'female',
            'identification_method' => 'visual',
            'status' => 'active',
            'acquisition_type' => 'born',
        ], $overrides);
    }
}
