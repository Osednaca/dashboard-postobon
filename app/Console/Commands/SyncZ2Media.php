<?php

namespace App\Console\Commands;

use App\Models\Media;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SyncZ2Media extends Command
{
    protected $signature = 'sync:z2-media';

    protected $description = 'Sync media from Z2 API';

    public function handle(): int
    {
        $this->info('Starting Z2 media sync...');

        $apiUrl = config('services.z2.api_url', 'https://api.z2.example.com');
        $apiKey = config('services.z2.api_key');

        if (! $apiKey) {
            $this->warn('Z2 API key not configured. Using mock sync.');
            $this->mockSync();

            return self::SUCCESS;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Accept' => 'application/json',
            ])->get($apiUrl . '/media');

            if (! $response->successful()) {
                $this->error('Z2 API request failed: ' . $response->status());
                Log::error('Z2 media sync failed', ['status' => $response->status(), 'body' => $response->body()]);

                return self::FAILURE;
            }

            $mediaItems = $response->json('data', []);
            $count = 0;
            $updated = 0;

            foreach ($mediaItems as $mediaData) {
                $originalName = $mediaData['original_name'] ?? ($mediaData['name'] ?? 'unknown');
                $extension = pathinfo($originalName, PATHINFO_EXTENSION);
                $extension = $extension ?: 'mp4';

                $media = Media::updateOrCreate(
                    ['name' => $mediaData['name'] ?? 'Unknown Media'],
                    [
                        'original_name' => $originalName,
                        'file_path' => $mediaData['file_path'] ?? ('media/z2/' . Str::uuid() . '.' . $extension),
                        'mime_type' => $mediaData['mime_type'] ?? ('video/' . $extension),
                        'size' => $mediaData['size'] ?? 0,
                        'duration' => $mediaData['duration'] ?? null,
                        'thumbnail' => $mediaData['thumbnail'] ?? null,
                    ]
                );

                if ($media->wasRecentlyCreated) {
                    $count++;
                } else {
                    $updated++;
                }

                if (! empty($mediaData['file_url'])) {
                    $this->info("Media {$media->name} available at: {$mediaData['file_url']}");
                }
            }

            $this->info("Z2 media sync completed. Created: {$count}, Updated: {$updated}");
            Log::info('Z2 media sync completed', ['created' => $count, 'updated' => $updated]);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Z2 media sync error: ' . $e->getMessage());
            Log::error('Z2 media sync error', ['exception' => $e]);

            return self::FAILURE;
        }
    }

    private function mockSync(): void
    {
        $this->info('Mock sync: checking existing media...');
        $mediaCount = Media::count();
        $this->info("Found {$mediaCount} media items in database.");
        $this->info('Mock sync complete. No external API called.');
    }
}
