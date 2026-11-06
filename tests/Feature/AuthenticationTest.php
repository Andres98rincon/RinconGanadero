<?php

namespace Tests\Feature;

use App\AccessControl;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_login_and_public_registration_is_disabled(): void
    {
        $this->logOutTestUser();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Inicia sesión')
            ->assertSee('Rincón Ganadero')
            ->assertSee('manifest.json')
            ->assertSee('images/rincon-ganadero.png')
            ->assertSee('data-pwa-install', false);
        $this->get('/register')->assertNotFound();
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_user_can_sign_in_and_sign_out(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create([
            'email' => 'operator@hatosoft.test',
            'password' => 'StrongPassword123',
        ]);
        $user->assignRole(Role::findByName(AccessControl::OPERATOR_ROLE, 'web'));
        $this->logOutTestUser();

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'StrongPassword123',
        ])->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Rincón Ganadero')
            ->assertSee('images/rincon-ganadero.png')
            ->assertSee('data-pwa-install', false);
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_invalid_credentials_do_not_authenticate(): void
    {
        $this->logOutTestUser();

        $this->post(route('login'), [
            'email' => 'unknown@hatosoft.test',
            'password' => 'WrongPassword123',
        ])->assertSessionHasErrors('email');

        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_inactive_and_google_only_accounts_cannot_sign_in_with_a_password(): void
    {
        $inactiveUser = User::factory()->create([
            'email' => 'inactive@hatosoft.test',
            'password' => 'StrongPassword123',
            'account_status' => 'inactive',
        ]);
        $googleUser = User::factory()->create([
            'email' => 'google-only@hatosoft.test',
            'password' => 'StrongPassword123',
            'auth_method' => 'google',
            'account_status' => 'active',
        ]);
        $this->logOutTestUser();

        $this->post(route('login'), [
            'email' => $inactiveUser->email,
            'password' => 'StrongPassword123',
        ])->assertSessionHasErrors('email');
        $this->post(route('login'), [
            'email' => $googleUser->email,
            'password' => 'StrongPassword123',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_user_can_request_a_password_reset_link(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'recover@hatosoft.test']);
        $this->logOutTestUser();

        $this->get(route('password.request'))->assertOk()->assertSee('Restablece tu acceso');
        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    }
}
