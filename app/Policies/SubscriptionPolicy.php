<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Subscription;

class SubscriptionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function view(User $user, Subscription $subscription): bool
    {
        return $user->role === 'admin';
    }

    public function create(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function update(User $user, Subscription $subscription): bool
    {
        return $user->role === 'admin';
    }

    public function delete(User $user, Subscription $subscription): bool
    {
        return $user->role === 'admin';
    }

    public function restore(User $user, Subscription $subscription): bool
    {
        return $user->role === 'admin';
    }

    public function forceDelete(User $user, Subscription $subscription): bool
    {
        return $user->role === 'admin';
    }
}
