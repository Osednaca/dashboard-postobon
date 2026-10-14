<?php

namespace App\Policies;

use App\Models\Establishment;
use App\Models\User;

class EstablishmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->managesEstablishments();
    }

    public function create(User $user): bool
    {
        return $user->managesEstablishments();
    }

    public function view(User $user, Establishment $establishment): bool
    {
        return $user->managesEstablishments();
    }

    public function update(User $user, Establishment $establishment): bool
    {
        return $user->managesEstablishments();
    }

    public function delete(User $user, Establishment $establishment): bool
    {
        return $user->managesEstablishments();
    }
}
