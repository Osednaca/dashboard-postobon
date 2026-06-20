<?php

namespace App\Services;

use App\Repositories\Contracts\BaseRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

abstract class BaseService
{
    /**
     * The repository instance.
     *
     * @var BaseRepositoryInterface
     */
    protected BaseRepositoryInterface $repository;

    /**
     * BaseService constructor.
     *
     * @param BaseRepositoryInterface $repository
     */
    public function __construct(BaseRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Get all records.
     *
     * @return Collection<int, Model>
     */
    public function all(): Collection
    {
        return $this->repository->all();
    }

    /**
     * Find a record by its primary key.
     *
     * @param int|string $id
     * @return Model|null
     */
    public function find(int|string $id): ?Model
    {
        return $this->repository->find($id);
    }

    /**
     * Create a new record.
     *
     * @param array<string, mixed> $data
     * @return Model
     */
    public function create(array $data): Model
    {
        return $this->repository->create($data);
    }

    /**
     * Update a record by its primary key.
     *
     * @param int|string $id
     * @param array<string, mixed> $data
     * @return Model|null
     */
    public function update(int|string $id, array $data): ?Model
    {
        return $this->repository->update($id, $data);
    }

    /**
     * Delete a record by its primary key.
     *
     * @param int|string $id
     * @return bool
     */
    public function delete(int|string $id): bool
    {
        return $this->repository->delete($id);
    }

    /**
     * Get paginated records.
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage);
    }
}
