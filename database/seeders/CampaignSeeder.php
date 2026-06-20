<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\Device;
use App\Models\Media;
use App\Models\User;
use Illuminate\Database\Seeder;

class CampaignSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();
        $devices = Device::all();
        $media = Media::all();

        $campaigns = Campaign::factory()
            ->count(15)
            ->recycle($users)
            ->create();

        foreach ($campaigns as $campaign) {
            $attachedMedia = $media->random(fake()->numberBetween(1, min(5, $media->count())));
            $campaign->media()->attach(
                $attachedMedia->pluck('id')->toArray(),
                ['order' => fake()->numberBetween(1, 10)]
            );

            $attachedDevices = $devices->random(fake()->numberBetween(1, min(10, $devices->count())));
            $campaign->deviceCampaigns()->createMany(
                $attachedDevices->map(fn (Device $device) => [
                    'device_id' => $device->id,
                    'status' => fake()->randomElement(['active', 'pending', 'completed', 'failed']),
                    'started_at' => fake()->optional(0.6)->dateTimeBetween('-30 days', 'now'),
                    'finished_at' => fake()->optional(0.3)->dateTimeBetween('-15 days', 'now'),
                ])->toArray()
            );
        }
    }
}
