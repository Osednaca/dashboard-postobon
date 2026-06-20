<?php

namespace App\Repositories\Contracts;

use App\Models\Device;
use Illuminate\Database\Eloquent\Collection;

interface DeviceRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Find a device by its MAC address.
     *
     * @param string $macAddress
     * @return Device|null
     */
    public function findByMac(string $macAddress): ?Device;

    /**
     * Update device status.
     *
     * @param int|string $id
     * @param string $status
     * @return Device|null
     */
    public function updateStatus(int|string $id, string $status): ?Device;

    /**
     * Update device heartbeat timestamp.
     *
     * @param int|string $id
     * @return Device|null
     */
    public function updateHeartbeat(int|string $id): ?Device;

    /**
     * Get devices by group ID.
     *
     * @param int|string $groupId
     * @return Collection<int, Device>
     */
    public function getByGroup(int|string $groupId): Collection;

    /**
     * Get devices by location ID.
     *
     * @param int|string $locationId
     * @return Collection<int, Device>
     */
    public function getByLocation(int|string $locationId): Collection;
}
