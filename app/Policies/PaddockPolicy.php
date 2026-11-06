<?php

namespace App\Policies;

use App\Models\Paddock;
use App\Models\User;

class PaddockPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('paddocks.view');
    }

    public function view(User $user, Paddock $paddock): bool
    {
        return $user->can('paddocks.view') && $user->canAccessFarm($paddock->farm_id);
    }

    public function create(User $user): bool
    {
        return $user->can('paddocks.create');
    }

    public function update(User $user, Paddock $paddock): bool
    {
        return $user->can('paddocks.update') && $user->canAccessFarm($paddock->farm_id);
    }

    public function delete(User $user, Paddock $paddock): bool
    {
        return $user->can('paddocks.delete') && $user->canAccessFarm($paddock->farm_id);
    }
}
