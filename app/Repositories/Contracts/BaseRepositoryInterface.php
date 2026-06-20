<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

interface BaseRepositoryInterface
{
    /**
     * Get all records.
     *
     * @return Collection<int, Model>
     */
    public function all(): Collection;

    /**
     * Find a record by its primary key.
     *
     * @param int|string $id
     * @return Model|null
     */
    public function find(int|string $id): ?Model;

    /**
     * Create a new record.
     *
     * @param array<string, mixed> $data
     * @return Model
     */
    public function create(array $data): Model;

    /**
     * Update a record by its primary key.
     *
     * @param int|string $id
     * @param array<string, mixed> $data
     * @return Model|null
     */
    public function update(int|string $id, array $data): ?Model;

    /**
     * Delete a record by its primary key.
     *
     * @param int|string $id
     * @return bool
     */
    public function delete(int|string $id): bool;

    /**
     * Get paginated records.
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator;
}
