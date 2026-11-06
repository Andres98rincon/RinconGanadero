<?php

namespace Tests\Feature;

use App\AccessControl;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_permission_seeder_creates_and_assigns_animal_import_permission(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $permission = Permission::findByName('animals.import', 'web');
        $administratorRole = Role::findByName(AccessControl::ADMIN_ROLE, 'web');

        $this->assertModelExists($permission);
        $this->assertTrue($administratorRole->hasPermissionTo($permission));
    }

    public function test_operator_can_read_finances_but_cannot_create_transactions(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $operator = User::factory()->create();
        $operator->assignRole(Role::findByName(AccessControl::OPERATOR_ROLE, 'web'));
        $this->actingAs($operator);

        $this->get(route('finance.index'))->assertOk();
        $this->get(route('finance.transactions.create'))->assertForbidden();
        $this->post(route('finance.transactions.store'), [])->assertForbidden();
    }

    public function test_administrator_can_manage_users_but_cannot_manage_roles_or_promote_another_administrator(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $administrator = User::factory()->create();
        $administrator->assignRole(Role::findByName(AccessControl::ADMIN_ROLE, 'web'));
        $this->actingAs($administrator);
        $adminRole = Role::findByName(AccessControl::ADMIN_ROLE, 'web');

        $this->get(route('admin.users.index'))->assertOk();
        $this->get(route('admin.roles.index'))->assertForbidden();
        $this->post(route('admin.users.store'), [
            'name' => 'Unauthorized Admin',
            'email' => 'unauthorized-admin@hatosoft.test',
            'password' => 'StrongPassword123',
            'password_confirmation' => 'StrongPassword123',
            'roles' => [$adminRole->id],
        ])->assertSessionHasErrors('roles.0');
    }

    public function test_super_administrator_can_create_a_role_and_assign_permissions(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $permission = Permission::findByName('reports.export', 'web');

        $this->get(route('admin.roles.index'))->assertOk();
        $this->post(route('admin.roles.store'), [
            'name' => 'supervisor_campo',
            'permissions' => [$permission->id],
        ])->assertRedirect(route('admin.roles.index'));

        $role = Role::findByName('supervisor_campo', 'web');
        $this->assertTrue($role->hasPermissionTo('reports.export'));
    }

    public function test_super_administrator_can_create_and_remove_an_unassigned_permission(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $this->post(route('admin.permissions.store'), ['name' => 'custom.inspect'])
            ->assertRedirect(route('admin.permissions.index'));

        $permission = Permission::findByName('custom.inspect', 'web');
        $this->delete(route('admin.permissions.destroy', $permission))
            ->assertRedirect(route('admin.permissions.index'));

        $this->assertDatabaseMissing('permissions', ['name' => 'custom.inspect']);
    }
}
