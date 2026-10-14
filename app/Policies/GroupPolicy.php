<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\User;

class GroupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->managesGroups();
    }

    public function view(User $user, Group $group): bool
    {
        return $user->managesGroups();
    }

    public function create(User $user): bool
    {
        return $user->createsGroups();
    }

    public function update(User $user, Group $group): bool
    {
        return $user->managesGroups();
    }

    public function delete(User $user, Group $group): bool
    {
        return $user->managesGroups();
    }

    public function restore(User $user, Group $group): bool
    {
        return $user->managesGroups();
    }

    public function forceDelete(User $user, Group $group): bool
    {
        return $user->managesGroups();
    }
}
