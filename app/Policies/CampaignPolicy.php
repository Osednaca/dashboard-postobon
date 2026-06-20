<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Campaign;

class CampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'operator']);
    }

    public function view(User $user, Campaign $campaign): bool
    {
        return in_array($user->role, ['admin', 'operator']);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'operator']);
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return in_array($user->role, ['admin', 'operator']);
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return in_array($user->role, ['admin', 'operator']);
    }

    public function restore(User $user, Campaign $campaign): bool
    {
        return in_array($user->role, ['admin', 'operator']);
    }

    public function forceDelete(User $user, Campaign $campaign): bool
    {
        return in_array($user->role, ['admin', 'operator']);
    }
}
