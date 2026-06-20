<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Location;

class LocationPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'operator']);
    }

    public function view(User $user, Location $location): bool
    {
        return in_array($user->role, ['admin', 'operator']);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'operator']);
    }

    public function update(User $user, Location $location): bool
    {
        return in_array($user->role, ['admin', 'operator']);
    }

    public function delete(User $user, Location $location): bool
    {
        return in_array($user->role, ['admin', 'operator']);
    }

    public function restore(User $user, Location $location): bool
    {
        return in_array($user->role, ['admin', 'operator']);
    }

    public function forceDelete(User $user, Location $location): bool
    {
        return in_array($user->role, ['admin', 'operator']);
    }
}
