<?php

namespace App\Repositories\Contracts;

use App\Models\User;

interface UserRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Find a user by email address.
     *
     * @param string $email
     * @return User|null
     */
    public function findByEmail(string $email): ?User;

    /**
     * Find users by role.
     *
     * @param string $role
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    public function findByRole(string $role): \Illuminate\Database\Eloquent\Collection;
}
