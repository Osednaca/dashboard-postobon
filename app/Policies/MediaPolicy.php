<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Media;

class MediaPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'operator']);
    }

    public function view(User $user, Media $media): bool
    {
        return in_array($user->role, ['admin', 'operator']);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'operator']);
    }

    public function update(User $user, Media $media): bool
    {
        return in_array($user->role, ['admin', 'operator']);
    }

    public function delete(User $user, Media $media): bool
    {
        return in_array($user->role, ['admin', 'operator']);
    }

    public function restore(User $user, Media $media): bool
    {
        return in_array($user->role, ['admin', 'operator']);
    }

    public function forceDelete(User $user, Media $media): bool
    {
        return in_array($user->role, ['admin', 'operator']);
    }
}
