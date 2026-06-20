<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 180;

    public function __construct()
    {
        $this->afterCommit();
    }

    public function handle(): void
    {
        Log::info('Sincronizando medios desde API externa...');

        // TODO: Integración con API externa para sincronizar medios
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SyncMediaJob falló: ' . $exception->getMessage());
    }
}
