<?php

namespace App\Policies;

use App\Models\Device;
use App\Models\User;

class DevicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->viewsDevices();
    }

    public function view(User $user, Device $device): bool
    {
        return $user->viewsDevices();
    }

    public function assignEstablishment(User $user): bool
    {
        return $user->assignsDevices();
    }

    public function reproduce(User $user): bool
    {
        return $user->managesOperations();
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
