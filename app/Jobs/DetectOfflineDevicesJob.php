<?php

namespace App\Jobs;

use App\Events\DeviceOffline;
use App\Models\Device;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DetectOfflineDevicesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $timeout = 120;

    public function __construct()
    {
        $this->afterCommit();
    }

    public function handle(): void
    {
        $threshold = now()->subMinutes(5);

        $offlineDevices = Device::where(function ($query) use ($threshold) {
            $query->whereNull('last_heartbeat_at')
                ->orWhere('last_heartbeat_at', '<', $threshold);
        })->where('status', '!=', 'offline')->get();

        foreach ($offlineDevices as $device) {
            $device->update(['status' => 'offline']);
            event(new DeviceOffline($device, $device->last_heartbeat_at));
        }

        Log::info("Detectados {$offlineDevices->count()} dispositivos fuera de línea.");
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('DetectOfflineDevicesJob falló: ' . $exception->getMessage());
    }
}
