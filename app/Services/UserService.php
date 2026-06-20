<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class UserService extends BaseService
{
    /**
     * UserService constructor.
     *
     * @param UserRepositoryInterface $userRepository
     */
    public function __construct(UserRepositoryInterface $userRepository)
    {
        parent::__construct($userRepository);
    }

    /**
     * Find a user by email.
     *
     * @param string $email
     * @return User|null
     */
    public function findByEmail(string $email): ?User
    {
        /** @var User|null */
        return $this->repository->findByEmail($email);
    }

    /**
     * Find users by role.
     *
     * @param string $role
     * @return Collection<int, User>
     */
    public function findByRole(string $role): Collection
    {
        return $this->repository->findByRole($role);
    }

    /**
     * Assign a role to a user.
     *
     * @param int|string $id
     * @param string $role
     * @return User|null
     */
    public function assignRole(int|string $id, string $role): ?User
    {
        $user = $this->repository->update($id, ['role' => $role]);

        /** @var User|null */
        return $user;
    }

    /**
     * @inheritDoc
     */
    public function create(array $data): Model
    {
        if (isset($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        }

        return parent::create($data);
    }

    /**
     * @inheritDoc
     */
    public function update(int|string $id, array $data): ?Model
    {
        if (isset($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        }

        return parent::update($id, $data);
    }
}
