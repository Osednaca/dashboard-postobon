<?php

namespace App\Listeners;

use App\Events\DeviceStatusChanged;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;

class SendDeviceStatusNotification
{
    public function handle(DeviceStatusChanged $event): void
    {
        $device = $event->device;

        Notification::create([
            'user_id' => null,
            'type' => 'device_status_changed',
            'title' => 'Cambio de estado del dispositivo',
            'message' => "El dispositivo {$device->name} cambió de estado de {$event->previousStatus} a {$event->newStatus}.",
            'data' => [
                'device_id' => $device->id,
                'previous_status' => $event->previousStatus,
                'new_status' => $event->newStatus,
            ],
        ]);

        Log::info("Dispositivo {$device->name} cambió de estado: {$event->previousStatus} -> {$event->newStatus}");
    }
}
