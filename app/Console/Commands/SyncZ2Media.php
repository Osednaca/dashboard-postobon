<?php

namespace App\Console\Commands;

use App\Services\Z2\Z2VideoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncZ2Media extends Command
{
    protected $signature = 'sync:z2-media';

    protected $description = 'Sync media from Z2 API';

    public function handle(Z2VideoService $z2VideoService): int
    {
        $this->info('Starting Z2 media sync with Z2 Cloud API...');

        try {
            $videos = $z2VideoService->syncVideos();
            $count = count($videos);

            $this->info("Z2 media sync completed successfully. Synced {$count} media files.");
            Log::info('Z2 media sync completed via CLI', ['count' => $count]);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Z2 media sync error: ' . $e->getMessage());
            Log::error('Z2 media sync error via CLI', ['exception' => $e]);

            return self::FAILURE;
        }
    }
}

