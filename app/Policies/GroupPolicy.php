<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Group;

class GroupPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'operator']);
    }

    public function view(User $user, Group $group): bool
    {
        return in_array($user->role, ['admin', 'operator']);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'operator']);
    }

    public function update(User $user, Group $group): bool
    {
        return in_array($user->role, ['admin', 'operator']);
    }

    public function delete(User $user, Group $group): bool
    {
        return in_array($user->role, ['admin', 'operator']);
    }

    public function restore(User $user, Group $group): bool
    {
        return in_array($user->role, ['admin', 'operator']);
    }

    public function forceDelete(User $user, Group $group): bool
    {
        return in_array($user->role, ['admin', 'operator']);
    }
}
