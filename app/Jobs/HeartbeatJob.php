<?php

namespace App\Jobs;

use App\Services\Z2\FanCloudService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class HeartbeatJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 30;

    public function handle(FanCloudService $client): void
    {
        try {
            Log::info('[HeartbeatJob] Sending heartbeat to Z2 Cloud');
            if (! $client->isAuthenticated()) {
                $client->authenticate();
            }
            Log::info('[HeartbeatJob] Heartbeat successful');
        } catch (\Throwable $e) {
            Log::error('[HeartbeatJob] Failed: ' . $e->getMessage());
        }
    }
}
