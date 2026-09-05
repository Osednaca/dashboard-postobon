<?php

namespace App\Policies;

use App\Models\Establishment;
use App\Models\User;

class EstablishmentPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'operator'], true);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'operator'], true);
    }

    public function view(User $user, Establishment $establishment): bool
    {
        return in_array($user->role, ['admin', 'operator'], true);
    }

    public function update(User $user, Establishment $establishment): bool
    {
        return in_array($user->role, ['admin', 'operator'], true);
    }

    public function delete(User $user, Establishment $establishment): bool
    {
        return in_array($user->role, ['admin', 'operator'], true);
    }
}
