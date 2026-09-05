<?php

namespace App\Policies;

use App\Models\BusinessType;
use App\Models\User;

class BusinessTypePolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'operator'], true);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'operator'], true);
    }

    public function update(User $user, BusinessType $businessType): bool
    {
        return in_array($user->role, ['admin', 'operator'], true);
    }

    public function delete(User $user, BusinessType $businessType): bool
    {
        return in_array($user->role, ['admin', 'operator'], true);
    }
}
