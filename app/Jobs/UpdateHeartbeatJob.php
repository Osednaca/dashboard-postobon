<?php

namespace App\Jobs;

use App\Models\Device;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateHeartbeatJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $timeout = 60;

    public function __construct(public Device $device)
    {
        $this->afterCommit();
    }

    public function handle(): void
    {
        $this->device->update([
            'last_heartbeat_at' => now(),
        ]);

        Log::info("Heartbeat actualizado para dispositivo: {$this->device->name}");
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('UpdateHeartbeatJob falló: ' . $exception->getMessage());
    }
}
