<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_profile(): void
    {
        $this->logOutTestUser();

        $this->get(route('profile.edit'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_update_name_and_email(): void
    {
        $user = User::factory()->create([
            'name' => 'Nombre anterior',
            'email' => 'anterior@hatosoft.test',
        ]);

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => 'Andrés Usuario',
                'email' => 'andres@hatosoft.test',
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('success', 'Tu información personal se actualizó correctamente.');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Andrés Usuario',
            'email' => 'andres@hatosoft.test',
        ]);
    }

    public function test_profile_rejects_an_email_owned_by_another_user(): void
    {
        $user = User::factory()->create(['email' => 'actual@hatosoft.test']);
        User::factory()->create(['email' => 'ocupado@hatosoft.test']);

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => 'Nombre sin cambio',
                'email' => 'ocupado@hatosoft.test',
            ])
            ->assertSessionHasErrors([
                'email' => 'Ese correo ya está asociado a otra cuenta.',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => $user->name,
            'email' => 'actual@hatosoft.test',
        ]);
    }

    public function test_profile_requires_a_name_and_a_valid_email(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => '',
                'email' => 'no-es-correo',
            ])
            ->assertSessionHasErrors([
                'name' => 'Escribe tu nombre completo.',
                'email' => 'Escribe una dirección de correo válida.',
            ]);
    }

    public function test_user_can_upload_replace_and_view_profile_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'photo' => UploadedFile::fake()->image('perfil.png'),
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('success', 'Tu perfil y fotografía se actualizaron correctamente.');

        $firstPhoto = $user->fresh()->getFirstMedia('profile-photo');
        $this->assertNotNull($firstPhoto);
        $this->assertSame('perfil.png', $firstPhoto->file_name);
        Storage::disk('public')->assertExists($firstPhoto->getPathRelativeToRoot());
        $this->assertTrue($firstPhoto->hasGeneratedConversion('optimized'));
        Storage::disk('public')->assertExists($firstPhoto->getPathRelativeToRoot('optimized'));

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'photo' => UploadedFile::fake()->image('perfil-nuevo.png'),
            ])
            ->assertRedirect(route('profile.edit'));

        $photos = $user->fresh()->getMedia('profile-photo');
        $this->assertCount(1, $photos);
        $this->assertSame('perfil-nuevo.png', $photos->first()->file_name);

        $this->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('profile-account-avatar')
            ->assertSee($user->fresh()->getFirstMedia('profile-photo')->getAvailableUrl(['optimized']), false);
    }

    public function test_user_can_remove_profile_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $user->addMedia(UploadedFile::fake()->image('perfil.png'))->toMediaCollection('profile-photo');

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'remove_photo' => '1',
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('success', 'Tu perfil se actualizó y la fotografía fue eliminada.');

        $this->assertCount(0, $user->fresh()->getMedia('profile-photo'));
    }

    public function test_profile_rejects_non_image_uploads(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'photo' => UploadedFile::fake()->create('perfil.svg', 20, 'image/svg+xml'),
            ])
            ->assertSessionHasErrors([
                'photo' => 'El archivo seleccionado debe ser una imagen válida.',
            ]);

        $this->assertCount(0, $user->fresh()->getMedia('profile-photo'));
    }

    public function test_profile_rejects_photos_larger_than_five_megabytes(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'photo' => UploadedFile::fake()->image('perfil.png')->size(5121),
            ])
            ->assertSessionHasErrors([
                'photo' => 'La foto no puede pesar más de 5 MB.',
            ]);

        $this->assertCount(0, $user->fresh()->getMedia('profile-photo'));
    }
}
