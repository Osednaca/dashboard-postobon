<?php

namespace App\Listeners;

use App\Events\DeviceOffline;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;

class SendOfflineAlert
{
    public function handle(DeviceOffline $event): void
    {
        $device = $event->device;
        $lastHeartbeat = $event->lastHeartbeatAt?->format('Y-m-d H:i:s') ?? 'desconocido';

        Notification::create([
            'user_id' => null,
            'type' => 'device_offline',
            'title' => 'Alerta: Dispositivo fuera de línea',
            'message' => "El dispositivo {$device->name} está fuera de línea. Último heartbeat: {$lastHeartbeat}.",
            'data' => [
                'device_id' => $device->id,
                'last_heartbeat_at' => $lastHeartbeat,
            ],
        ]);

        Log::warning("Dispositivo {$device->name} está fuera de línea. Último heartbeat: {$lastHeartbeat}");
    }
}
