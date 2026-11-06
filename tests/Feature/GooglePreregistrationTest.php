<?php

namespace Tests\Feature;

use App\AccessControl;
use App\Models\Farm;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GooglePreregistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_create_a_pending_google_preregistration_without_a_password(): void
    {
        $this->logOutTestUser();
        $this->seed(RolePermissionSeeder::class);
        $farm = Farm::factory()->create();
        $administrator = User::factory()->create();
        $administrator->assignRole(Role::findByName(AccessControl::ADMIN_ROLE, 'web'));
        $administrator->farms()->attach($farm);
        $operatorRole = Role::findByName(AccessControl::OPERATOR_ROLE, 'web');

        $response = $this->actingAs($administrator)->post(route('admin.users.store'), [
            'name' => 'Google Operator',
            'email' => 'google-operator@hatosoft.test',
            'auth_method' => 'google',
            'account_status' => 'active',
            'roles' => [$operatorRole->id],
            'farm_ids' => [$farm->id],
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $user = User::query()->where('email', 'google-operator@hatosoft.test')->firstOrFail();
        $this->assertSame('pending', $user->account_status);
        $this->assertSame('google', $user->auth_method);
        $this->assertNull($user->password);
        $this->assertTrue($user->hasRole(AccessControl::OPERATOR_ROLE));
    }

    public function test_verified_google_preregistration_links_and_activates_the_existing_user(): void
    {
        $this->logOutTestUser();
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create([
            'name' => 'Nombre autorizado',
            'email' => 'authorized@hatosoft.test',
            'password' => null,
            'auth_method' => 'google',
            'account_status' => 'pending',
        ]);
        $user->assignRole(Role::findByName(AccessControl::OPERATOR_ROLE, 'web'));
        Socialite::fake('google', GoogleUser::fake([
            'id' => 'google-sub-101',
            'email' => 'authorized@hatosoft.test',
            'email_verified' => true,
        ]));

        $response = $this->get(route('google.callback'));

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'google_id' => 'google-sub-101',
            'account_status' => 'active',
            'auth_method' => 'google',
        ]);
        $this->assertNotNull($user->fresh()->first_login_at);
        $this->assertTrue($user->fresh()->hasRole(AccessControl::OPERATOR_ROLE));
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'authentication',
            'event' => 'google_login',
        ]);
    }

    public function test_unregistered_or_unverified_google_accounts_are_rejected_without_creating_users(): void
    {
        $this->logOutTestUser();
        $this->seed(RolePermissionSeeder::class);
        $unverifiedUser = User::factory()->create([
            'email' => 'pending@hatosoft.test',
            'password' => null,
            'auth_method' => 'google',
            'account_status' => 'pending',
        ]);
        Socialite::fake('google', GoogleUser::fake([
            'id' => 'google-unverified',
            'email' => 'pending@hatosoft.test',
            'email_verified' => false,
        ]));

        $this->get(route('google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('users', [
            'id' => $unverifiedUser->id,
            'google_id' => null,
            'account_status' => 'pending',
        ]);

        Socialite::fake('google', GoogleUser::fake([
            'id' => 'google-unregistered',
            'email' => 'unknown@hatosoft.test',
            'email_verified' => true,
        ]));

        $this->get(route('google.callback'))->assertRedirect(route('login'));
        $this->assertDatabaseMissing('users', ['email' => 'unknown@hatosoft.test']);
    }

    public function test_an_active_google_account_cannot_be_relinked_to_another_google_identity(): void
    {
        $this->logOutTestUser();
        $user = User::factory()->create([
            'email' => 'linked@hatosoft.test',
            'password' => null,
            'auth_method' => 'google',
            'account_status' => 'active',
        ]);
        $user->forceFill(['google_id' => 'google-sub-original'])->save();
        Socialite::fake('google', GoogleUser::fake([
            'id' => 'google-sub-attacker',
            'email' => 'linked@hatosoft.test',
            'email_verified' => true,
        ]));

        $this->get(route('google.callback'))->assertRedirect(route('login'));
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'google_id' => 'google-sub-original',
            'account_status' => 'active',
        ]);
    }
}
