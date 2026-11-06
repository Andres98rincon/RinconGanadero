<?php

namespace App\Policies;

use App\Models\Farm;
use App\Models\User;

class FarmPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('farms.view');
    }

    public function view(User $user, Farm $farm): bool
    {
        return $user->can('farms.view') && $user->canAccessFarm($farm->id);
    }

    public function create(User $user): bool
    {
        return $user->can('farms.create');
    }

    public function update(User $user, Farm $farm): bool
    {
        return $user->can('farms.update') && $user->canAccessFarm($farm->id);
    }

    public function delete(User $user, Farm $farm): bool
    {
        return $user->can('farms.delete') && $user->canAccessFarm($farm->id);
    }
}
