<?php

namespace App\Policies;

use App\Models\Device;
use App\Models\User;

class DevicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->operatesDevices();
    }

    public function view(User $user, Device $device): bool
    {
        return $user->operatesDevices();
    }

    public function create(User $user): bool
    {
        return $user->operatesDevices();
    }

    public function update(User $user, Device $device): bool
    {
        return $user->operatesDevices();
    }

    public function delete(User $user, Device $device): bool
    {
        return $user->operatesDevices();
    }

    public function restore(User $user, Device $device): bool
    {
        return $user->operatesDevices();
    }

    public function forceDelete(User $user, Device $device): bool
    {
        return $user->operatesDevices();
    }
}
