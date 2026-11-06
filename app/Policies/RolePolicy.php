<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('roles.view');
    }

    public function create(User $user): bool
    {
        return $user->can('roles.create');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->can('roles.update') && ! $this->isSystemRole($role);
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->can('roles.delete') && ! $this->isSystemRole($role);
    }

    private function isSystemRole(Role $role): bool
    {
        return in_array($role->name, ['administrador', 'superadministrador', 'operador'], true);
    }
}
