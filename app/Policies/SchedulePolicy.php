<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Schedule;

class SchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'operator']);
    }

    public function view(User $user, Schedule $schedule): bool
    {
        return in_array($user->role, ['admin', 'operator']);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'operator']);
    }

    public function update(User $user, Schedule $schedule): bool
    {
        return in_array($user->role, ['admin', 'operator']);
    }

    public function delete(User $user, Schedule $schedule): bool
    {
        return in_array($user->role, ['admin', 'operator']);
    }

    public function restore(User $user, Schedule $schedule): bool
    {
        return in_array($user->role, ['admin', 'operator']);
    }

    public function forceDelete(User $user, Schedule $schedule): bool
    {
        return in_array($user->role, ['admin', 'operator']);
    }
}
