<?php

namespace App\Events;

use App\Models\Device;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeviceOffline
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Device $device;
    public ?\DateTime $lastHeartbeatAt;

    public function __construct(Device $device, ?\DateTime $lastHeartbeatAt = null)
    {
        $this->device = $device;
        $this->lastHeartbeatAt = $lastHeartbeatAt;
    }
}
