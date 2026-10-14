<?php

namespace App\Policies;

use App\Models\Schedule;
use App\Models\User;

class SchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->operatesDevices();
    }

    public function view(User $user, Schedule $schedule): bool
    {
        return $user->operatesDevices() && ($user->managesOperations()
            || ($schedule->type !== 'activate_campaign' && $schedule->campaign_id === null));
    }

    public function create(User $user): bool
    {
        return $user->operatesDevices();
    }

    public function update(User $user, Schedule $schedule): bool
    {
        return $this->view($user, $schedule);
    }

    public function delete(User $user, Schedule $schedule): bool
    {
        return $this->view($user, $schedule);
    }

    public function restore(User $user, Schedule $schedule): bool
    {
        return $this->view($user, $schedule);
    }

    public function forceDelete(User $user, Schedule $schedule): bool
    {
        return $this->view($user, $schedule);
    }
}
