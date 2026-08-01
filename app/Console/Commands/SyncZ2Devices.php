<?php

namespace App\Console\Commands;

use App\Services\Z2\Z2DeviceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncZ2Devices extends Command
{
    protected $signature = 'sync:z2-devices';

    protected $description = 'Sync devices from Z2 API';

    public function handle(Z2DeviceService $z2DeviceService): int
    {
        $this->info('Starting Z2 device sync with Z2 Cloud API...');

        try {
            $devices = $z2DeviceService->syncDevices();
            $count = count($devices);

            $this->info("Z2 device sync completed successfully. Synced {$count} devices.");
            Log::info('Z2 device sync completed via CLI', ['count' => $count]);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Z2 device sync error: ' . $e->getMessage());
            Log::error('Z2 device sync error via CLI', ['exception' => $e]);

            return self::FAILURE;
        }
    }
}

