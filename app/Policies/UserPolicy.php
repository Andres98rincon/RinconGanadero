<?php

namespace App\Policies;

use App\AccessControl;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.view');
    }

    public function view(User $user, User $model): bool
    {
        return $user->can('users.view')
            && ! $model->hasRole(AccessControl::SUPER_ADMIN_ROLE)
            && $user->canAccessUser($model);
    }

    public function create(User $user): bool
    {
        return $user->can('users.create');
    }

    public function update(User $user, User $model): bool
    {
        return $user->can('users.update')
            && ! $model->hasRole(AccessControl::SUPER_ADMIN_ROLE)
            && $user->canAccessUser($model)
            && ($user->is($model)
                || ! $model->hasRole(AccessControl::ADMIN_ROLE)
                || $user->hasRole(AccessControl::SUPER_ADMIN_ROLE));
    }

    public function delete(User $user, User $model): bool
    {
        return $user->can('users.delete')
            && $user->isNot($model)
            && ! $model->hasAnyRole([AccessControl::SUPER_ADMIN_ROLE, AccessControl::ADMIN_ROLE])
            && $user->canAccessUser($model);
    }
}
