<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Device;

class DevicePolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'operator']);
    }

    public function view(User $user, Device $device): bool
    {
        return in_array($user->role, ['admin', 'operator']);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'operator']);
    }

    public function update(User $user, Device $device): bool
    {
        return in_array($user->role, ['admin', 'operator']);
    }

    public function delete(User $user, Device $device): bool
    {
        return in_array($user->role, ['admin', 'operator']);
    }

    public function restore(User $user, Device $device): bool
    {
        return in_array($user->role, ['admin', 'operator']);
    }

    public function forceDelete(User $user, Device $device): bool
    {
        return in_array($user->role, ['admin', 'operator']);
    }
}
