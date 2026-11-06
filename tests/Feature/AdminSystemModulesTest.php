<?php

namespace Tests\Feature;

use App\AccessControl;
use App\Models\Farm;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminSystemModulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_view_scoped_audit_logs_but_not_global_backups(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $farm = Farm::factory()->create();
        $administrator = User::factory()->create();
        $administrator->assignRole(Role::findByName(AccessControl::ADMIN_ROLE, 'web'));
        $administrator->farms()->attach($farm);
        $this->actingAs($administrator);

        $this->get(route('admin.activity-log.index'))->assertOk()->assertSee('Bitácora de auditoría');
        $this->get(route('admin.backups.index'))->assertForbidden();
    }

    public function test_operator_cannot_view_audit_logs_or_manage_backups(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $operator = User::factory()->create();
        $operator->assignRole(Role::findByName(AccessControl::OPERATOR_ROLE, 'web'));
        $this->actingAs($operator);

        $this->get(route('admin.activity-log.index'))->assertForbidden();
        $this->get(route('admin.backups.index'))->assertForbidden();
        $this->post(route('admin.backups.store'))->assertForbidden();
    }
}
