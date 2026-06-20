<?php

namespace App\Jobs;

use App\Services\Z2\Z2VideoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncVideosJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function handle(Z2VideoService $videoService): void
    {
        try {
            Log::info('[SyncVideosJob] Starting video sync from Z2 Cloud');
            $videos = $videoService->syncVideos();
            Log::info('[SyncVideosJob] Synced ' . count($videos) . ' videos');
        } catch (\Throwable $e) {
            Log::error('[SyncVideosJob] Failed: ' . $e->getMessage());
            $this->fail($e);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[SyncVideosJob] Job failed: ' . $exception->getMessage());
    }
}
