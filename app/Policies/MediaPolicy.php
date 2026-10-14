<?php

namespace App\Policies;

use App\Models\Media;
use App\Models\User;

class MediaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->operatesDevices();
    }

    public function view(User $user, Media $media): bool
    {
        return $user->operatesDevices();
    }

    public function create(User $user): bool
    {
        return $user->operatesDevices();
    }

    public function update(User $user, Media $media): bool
    {
        return $user->operatesDevices();
    }

    public function delete(User $user, Media $media): bool
    {
        return $user->operatesDevices();
    }

    public function restore(User $user, Media $media): bool
    {
        return $user->operatesDevices();
    }

    public function forceDelete(User $user, Media $media): bool
    {
        return $user->operatesDevices();
    }
}
