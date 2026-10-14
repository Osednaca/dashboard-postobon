<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\User;

class GroupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->operatesDevices();
    }

    public function view(User $user, Group $group): bool
    {
        return $user->operatesDevices();
    }

    public function create(User $user): bool
    {
        return $user->operatesDevices();
    }

    public function update(User $user, Group $group): bool
    {
        return $user->operatesDevices();
    }

    public function delete(User $user, Group $group): bool
    {
        return $user->operatesDevices();
    }

    public function restore(User $user, Group $group): bool
    {
        return $user->operatesDevices();
    }

    public function forceDelete(User $user, Group $group): bool
    {
        return $user->operatesDevices();
    }
}
