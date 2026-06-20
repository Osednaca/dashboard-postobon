<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncZ2Campaigns extends Command
{
    protected $signature = 'sync:z2-campaigns';

    protected $description = 'Sync campaigns from Z2 API';

    public function handle(): int
    {
        $this->info('Starting Z2 campaign sync...');

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
            ])->get($apiUrl . '/campaigns');

            if (! $response->successful()) {
                $this->error('Z2 API request failed: ' . $response->status());
                Log::error('Z2 campaign sync failed', ['status' => $response->status(), 'body' => $response->body()]);

                return self::FAILURE;
            }

            $campaigns = $response->json('data', []);
            $count = 0;
            $updated = 0;

            $adminUser = User::where('role', 'admin')->first();
            $createdBy = $adminUser?->id ?? User::factory()->admin()->create()->id;

            foreach ($campaigns as $campaignData) {
                $campaign = Campaign::updateOrCreate(
                    ['name' => $campaignData['name'] ?? 'Unknown Campaign'],
                    [
                        'description' => $campaignData['description'] ?? null,
                        'status' => $campaignData['status'] ?? 'draft',
                        'priority' => $campaignData['priority'] ?? 0,
                        'start_date' => $campaignData['start_date'] ?? null,
                        'end_date' => $campaignData['end_date'] ?? null,
                        'segment_cities' => $campaignData['segment_cities'] ?? null,
                        'segment_groups' => $campaignData['segment_groups'] ?? null,
                        'created_by' => $createdBy,
                    ]
                );

                if ($campaign->wasRecentlyCreated) {
                    $count++;
                } else {
                    $updated++;
                }

                if (! empty($campaignData['media'])) {
                    $mediaIds = collect($campaignData['media'])->pluck('id')->toArray();
                    $campaign->media()->syncWithPivotValues($mediaIds, ['order' => 1]);
                }

                if (! empty($campaignData['devices'])) {
                    foreach ($campaignData['devices'] as $deviceData) {
                        $campaign->deviceCampaigns()->updateOrCreate(
                            ['device_id' => $deviceData['id']],
                            [
                                'status' => $deviceData['status'] ?? 'pending',
                                'started_at' => $deviceData['started_at'] ?? null,
                                'finished_at' => $deviceData['finished_at'] ?? null,
                            ]
                        );
                    }
                }
            }

            $this->info("Z2 campaign sync completed. Created: {$count}, Updated: {$updated}");
            Log::info('Z2 campaign sync completed', ['created' => $count, 'updated' => $updated]);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Z2 campaign sync error: ' . $e->getMessage());
            Log::error('Z2 campaign sync error', ['exception' => $e]);

            return self::FAILURE;
        }
    }

    private function mockSync(): void
    {
        $this->info('Mock sync: checking existing campaigns...');
        $campaignCount = Campaign::count();
        $this->info("Found {$campaignCount} campaigns in database.");
        $this->info('Mock sync complete. No external API called.');
    }
}
