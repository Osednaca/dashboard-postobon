<?php

namespace App\Policies;

use App\Models\Establishment;
use App\Models\User;

class EstablishmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->operatesDevices();
    }

    public function create(User $user): bool
    {
        return $user->operatesDevices();
    }

    public function view(User $user, Establishment $establishment): bool
    {
        return $user->operatesDevices();
    }

    public function update(User $user, Establishment $establishment): bool
    {
        return $user->operatesDevices();
    }

    public function delete(User $user, Establishment $establishment): bool
    {
        return $user->operatesDevices();
    }
}
