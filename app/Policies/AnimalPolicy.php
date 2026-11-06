<?php

namespace App\Policies;

use App\Models\Animal;
use App\Models\User;

class AnimalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('animals.view');
    }

    public function view(User $user, Animal $animal): bool
    {
        return $user->can('animals.view') && $user->canAccessFarm($animal->farm_id);
    }

    public function create(User $user): bool
    {
        return $user->can('animals.create');
    }

    public function update(User $user, Animal $animal): bool
    {
        return $user->can('animals.update') && $user->canAccessFarm($animal->farm_id);
    }

    public function delete(User $user, Animal $animal): bool
    {
        return $user->can('animals.delete') && $user->canAccessFarm($animal->farm_id);
    }
}
