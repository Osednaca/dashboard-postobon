<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserService extends BaseService
{
    /**
     * UserService constructor.
     */
    public function __construct(UserRepositoryInterface $userRepository)
    {
        parent::__construct($userRepository);
    }

    /**
     * Find a user by email.
     */
    public function findByEmail(string $email): ?User
    {
        /** @var User|null */
        return $this->repository->findByEmail($email);
    }

    /**
     * Find users by role.
     *
     * @return Collection<int, User>
     */
    public function findByRole(string $role): Collection
    {
        return $this->repository->findByRole($role);
    }

    /**
     * Assign a role to a user.
     */
    public function assignRole(int|string $id, string $role): ?User
    {
        $user = $this->update($id, ['role' => $role]);

        /** @var User|null */
        return $user;
    }

    /**
     * {@inheritDoc}
     */
    public function create(array $data): Model
    {
        if (isset($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        }

        return parent::create($data);
    }

    /**
     * {@inheritDoc}
     */
    public function update(int|string $id, array $data): ?Model
    {
        if (! empty($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        } else {
            unset($data['password']);
        }

        return DB::transaction(function () use ($id, $data) {
            $superadmins = User::where('role', 'superadmin')->orderBy('id')->lockForUpdate()->get();
            $user = User::lockForUpdate()->find($id);

            if ($user?->isSuperadmin() && isset($data['role']) && $data['role'] !== 'superadmin' && $superadmins->count() <= 1) {
                throw ValidationException::withMessages(['role' => 'Debe existir al menos un superadministrador.']);
            }

            return parent::update($id, $data);
        });
    }

    public function delete(int|string $id): bool
    {
        return DB::transaction(function () use ($id) {
            $superadmins = User::where('role', 'superadmin')->orderBy('id')->lockForUpdate()->get();
            $user = User::lockForUpdate()->find($id);

            if ($user && ($user->is(auth()->user()) || ($user->isSuperadmin() && $superadmins->count() <= 1))) {
                throw ValidationException::withMessages(['user' => 'No puedes eliminar tu cuenta ni el último superadministrador.']);
            }

            return parent::delete($id);
        });
    }
}
