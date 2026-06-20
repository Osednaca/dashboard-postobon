<?php

namespace App\Jobs;

use App\Services\Z2\Z2DeviceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncDevicesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function handle(Z2DeviceService $deviceService): void
    {
        try {
            Log::info('[SyncDevicesJob] Starting device sync from Z2 Cloud');
            $devices = $deviceService->syncDevices();
            Log::info('[SyncDevicesJob] Synced ' . count($devices) . ' devices');
        } catch (\Throwable $e) {
            Log::error('[SyncDevicesJob] Failed: ' . $e->getMessage());
            $this->fail($e);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[SyncDevicesJob] Job failed: ' . $exception->getMessage());
    }
}
