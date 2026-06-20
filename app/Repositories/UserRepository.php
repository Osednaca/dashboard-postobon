<?php

namespace App\Repositories;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    /**
     * UserRepository constructor.
     *
     * @param User $user
     */
    public function __construct(User $user)
    {
        parent::__construct($user);
    }

    /**
     * @inheritDoc
     */
    public function findByEmail(string $email): ?User
    {
        /** @var User|null */
        return $this->model->where('email', $email)->first();
    }

    /**
     * @inheritDoc
     */
    public function findByRole(string $role): Collection
    {
        return $this->model->where('role', $role)->get();
    }
}
