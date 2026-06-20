<?php

namespace App\Events;

use App\Models\Device;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeviceStatusChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Device $device;
    public string $previousStatus;
    public string $newStatus;

    public function __construct(Device $device, string $previousStatus, string $newStatus)
    {
        $this->device = $device;
        $this->previousStatus = $previousStatus;
        $this->newStatus = $newStatus;
    }
}
