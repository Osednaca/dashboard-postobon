<?php

namespace App\Jobs;

use App\Models\Device;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncDevicesStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;

    public function __construct()
    {
        $this->afterCommit();
    }

    public function handle(): void
    {
        Log::info('Sincronizando estado de dispositivos desde API externa...');

        // TODO: Integración con API externa para sincronizar estado
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SyncDevicesStatusJob falló: ' . $exception->getMessage());
    }
}
