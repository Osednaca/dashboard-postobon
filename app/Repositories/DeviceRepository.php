<?php

namespace App\Repositories;

use App\Models\Device;
use App\Repositories\Contracts\DeviceRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class DeviceRepository extends BaseRepository implements DeviceRepositoryInterface
{
    /**
     * DeviceRepository constructor.
     *
     * @param Device $device
     */
    public function __construct(Device $device)
    {
        parent::__construct($device);
    }

    /**
     * @inheritDoc
     */
    public function findByMac(string $macAddress): ?Device
    {
        /** @var Device|null */
        return $this->model->where('mac_address', $macAddress)->first();
    }

    /**
     * @inheritDoc
     */
    public function updateStatus(int|string $id, string $status): ?Device
    {
        return $this->update($id, ['status' => $status]);
    }

    /**
     * @inheritDoc
     */
    public function updateHeartbeat(int|string $id): ?Device
    {
        return $this->update($id, [
            'last_heartbeat_at' => Carbon::now(),
        ]);
    }

    /**
     * @inheritDoc
     */
    public function getByGroup(int|string $groupId): Collection
    {
        return $this->model->where('group_id', $groupId)->get();
    }

    /**
     * @inheritDoc
     */
    public function getByLocation(int|string $locationId): Collection
    {
        return $this->model->where('location_id', $locationId)->get();
    }
}
