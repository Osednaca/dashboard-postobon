<?php

namespace App\Policies;

use App\Models\Notification;
use App\Models\User;

class NotificationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperadmin();
    }

    public function view(User $user, Notification $notification): bool
    {
        return $user->isSuperadmin() && $user->id === $notification->user_id;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Notification $notification): bool
    {
        return $user->isSuperadmin() && $user->id === $notification->user_id;
    }

    public function delete(User $user, Notification $notification): bool
    {
        return $user->isSuperadmin() && $user->id === $notification->user_id;
    }

    public function restore(User $user, Notification $notification): bool
    {
        return false;
    }

    public function forceDelete(User $user, Notification $notification): bool
    {
        return false;
    }
}
