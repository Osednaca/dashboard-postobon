<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->managesOperations();
    }

    public function view(User $user, User $model): bool
    {
        return $user->is($model) || $this->update($user, $model);
    }

    public function create(User $user): bool
    {
        return $user->managesOperations();
    }

    public function update(User $user, User $model): bool
    {
        return $user->isSuperadmin() || ($user->role === 'admin' && $model->role === 'tecnico');
    }

    public function delete(User $user, User $model): bool
    {
        return ! $user->is($model)
            && $this->update($user, $model)
            && (! $model->isSuperadmin() || User::where('role', 'superadmin')->whereKeyNot($model->id)->exists());
    }

    public function restore(User $user, User $model): bool
    {
        return $this->update($user, $model);
    }

    public function forceDelete(User $user, User $model): bool
    {
        return $this->delete($user, $model);
    }
}
