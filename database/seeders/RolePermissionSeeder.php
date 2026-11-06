<?php

namespace Database\Seeders;

use App\AccessControl;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (array_keys(AccessControl::PERMISSION_LABELS) as $permissionName) {
            Permission::query()->updateOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        Role::findOrCreate(AccessControl::SUPER_ADMIN_ROLE, 'web')
            ->syncPermissions(array_keys(AccessControl::PERMISSION_LABELS));

        Role::findOrCreate(AccessControl::ADMIN_ROLE, 'web')
            ->syncPermissions(AccessControl::ADMIN_PERMISSIONS);

        Role::findOrCreate(AccessControl::OPERATOR_ROLE, 'web')
            ->syncPermissions(AccessControl::OPERATOR_PERMISSIONS);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
