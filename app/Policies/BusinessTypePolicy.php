<?php

namespace App\Policies;

use App\Models\BusinessType;
use App\Models\User;

class BusinessTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->operatesDevices();
    }

    public function create(User $user): bool
    {
        return $user->operatesDevices();
    }

    public function update(User $user, BusinessType $businessType): bool
    {
        return $user->operatesDevices();
    }

    public function delete(User $user, BusinessType $businessType): bool
    {
        return $user->operatesDevices();
    }
}
