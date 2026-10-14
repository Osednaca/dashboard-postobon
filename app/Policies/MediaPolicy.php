<?php

namespace App\Policies;

use App\Models\Media;
use App\Models\User;

class MediaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->usesMedia();
    }

    public function view(User $user, Media $media): bool
    {
        return $user->usesMedia();
    }

    public function create(User $user): bool
    {
        return $user->usesMedia();
    }

    public function update(User $user, Media $media): bool
    {
        return $user->usesMedia();
    }

    public function delete(User $user, Media $media): bool
    {
        return $user->usesMedia();
    }

    public function restore(User $user, Media $media): bool
    {
        return $user->usesMedia();
    }

    public function forceDelete(User $user, Media $media): bool
    {
        return $user->usesMedia();
    }
}
