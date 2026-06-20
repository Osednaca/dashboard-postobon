<?php

namespace App\Jobs;

use App\Services\Z2\Z2GroupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncGroupsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    public function handle(Z2GroupService $groupService): void
    {
        try {
            Log::info('[SyncGroupsJob] Starting group sync from Z2 Cloud');
            $groups = $groupService->syncGroups();
            Log::info('[SyncGroupsJob] Synced ' . count($groups) . ' groups');
        } catch (\Throwable $e) {
            Log::error('[SyncGroupsJob] Failed: ' . $e->getMessage());
            $this->fail($e);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[SyncGroupsJob] Job failed: ' . $exception->getMessage());
    }
}
