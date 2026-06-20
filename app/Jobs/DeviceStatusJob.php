<?php

namespace App\Jobs;

use App\Services\Z2\Z2DeviceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DeviceStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 60;

    public function handle(Z2DeviceService $deviceService): void
    {
        try {
            Log::info('[DeviceStatusJob] Checking device status from Z2 Cloud');
            $deviceService->syncDevices();
            Log::info('[DeviceStatusJob] Device status updated');
        } catch (\Throwable $e) {
            Log::error('[DeviceStatusJob] Failed: ' . $e->getMessage());
        }
    }
}
