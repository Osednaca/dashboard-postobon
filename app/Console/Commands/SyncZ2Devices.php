<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\Group;
use App\Models\Location;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncZ2Devices extends Command
{
    protected $signature = 'sync:z2-devices';

    protected $description = 'Sync devices from Z2 API';

    public function handle(): int
    {
        $this->info('Starting Z2 device sync...');

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
            ])->get($apiUrl . '/devices');

            if (! $response->successful()) {
                $this->error('Z2 API request failed: ' . $response->status());
                Log::error('Z2 device sync failed', ['status' => $response->status(), 'body' => $response->body()]);

                return self::FAILURE;
            }

            $devices = $response->json('data', []);
            $count = 0;
            $updated = 0;

            foreach ($devices as $deviceData) {
                $device = Device::updateOrCreate(
                    ['mac_address' => $deviceData['mac_address'] ?? null],
                    [
                        'name' => $deviceData['name'] ?? 'Z2 Device ' . ($deviceData['id'] ?? 'Unknown'),
                        'firmware' => $deviceData['firmware'] ?? null,
                        'hardware' => $deviceData['hardware'] ?? null,
                        'rpm' => $deviceData['rpm'] ?? null,
                        'status' => $deviceData['status'] ?? 'offline',
                        'power_status' => $deviceData['power_status'] ?? 'off',
                        'last_heartbeat_at' => $deviceData['last_heartbeat_at'] ?? null,
                    ]
                );

                if ($device->wasRecentlyCreated) {
                    $count++;
                } else {
                    $updated++;
                }

                if (! empty($deviceData['location'])) {
                    $location = Location::firstOrCreate(
                        ['name' => $deviceData['location']['name']],
                        [
                            'address' => $deviceData['location']['address'] ?? null,
                            'city' => $deviceData['location']['city'] ?? null,
                            'country' => $deviceData['location']['country'] ?? null,
                            'latitude' => $deviceData['location']['latitude'] ?? null,
                            'longitude' => $deviceData['location']['longitude'] ?? null,
                        ]
                    );
                    $device->location()->associate($location);
                    $device->save();
                }

                if (! empty($deviceData['group'])) {
                    $group = Group::firstOrCreate(
                        ['name' => $deviceData['group']['name']],
                        ['description' => $deviceData['group']['description'] ?? null]
                    );
                    $device->group()->associate($group);
                    $device->save();
                }
            }

            $this->info("Z2 device sync completed. Created: {$count}, Updated: {$updated}");
            Log::info('Z2 device sync completed', ['created' => $count, 'updated' => $updated]);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Z2 device sync error: ' . $e->getMessage());
            Log::error('Z2 device sync error', ['exception' => $e]);

            return self::FAILURE;
        }
    }

    private function mockSync(): void
    {
        $this->info('Mock sync: checking existing devices...');
        $deviceCount = Device::count();
        $this->info("Found {$deviceCount} devices in database.");
        $this->info('Mock sync complete. No external API called.');
    }
}
