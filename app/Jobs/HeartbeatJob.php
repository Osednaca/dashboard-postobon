<?php

namespace App\Jobs;

use App\Services\PrivateCloud\PrivateCloudClient;
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

    public function handle(PrivateCloudClient $client): void
    {
        try {
            Log::info('[HeartbeatJob] Checking connection to private cloud');
            if (! $client->ping()) {
                Log::error('[HeartbeatJob] Private cloud unreachable');
                return;
            }
            Log::info('[HeartbeatJob] Connection OK');
        } catch (\Throwable $e) {
            Log::error('[HeartbeatJob] Failed: ' . $e->getMessage());
        }
    }
}
