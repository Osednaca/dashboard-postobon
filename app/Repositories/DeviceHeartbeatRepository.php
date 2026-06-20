<?php

namespace App\Repositories;

use App\Models\DeviceHeartbeat;
use App\Repositories\Contracts\DeviceHeartbeatRepositoryInterface;

class DeviceHeartbeatRepository extends BaseRepository implements DeviceHeartbeatRepositoryInterface
{
    /**
     * DeviceHeartbeatRepository constructor.
     *
     * @param DeviceHeartbeat $deviceHeartbeat
     */
    public function __construct(DeviceHeartbeat $deviceHeartbeat)
    {
        parent::__construct($deviceHeartbeat);
    }
}
