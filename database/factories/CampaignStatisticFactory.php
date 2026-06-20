<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\CampaignStatistic;
use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;

class CampaignStatisticFactory extends Factory
{
    protected $model = CampaignStatistic::class;

    public function definition(): array
    {
        $impressions = fake()->numberBetween(100, 50000);
        $plays = (int) round($impressions * fake()->randomFloat(2, 0.5, 0.95));
        $duration = fake()->randomFloat(2, 5, 300);

        return [
            'campaign_id' => Campaign::factory(),
            'device_id' => Device::factory(),
            'impressions' => $impressions,
            'plays' => $plays,
            'duration' => $duration,
            'date' => fake()->dateTimeBetween('-90 days', 'now')->format('Y-m-d'),
        ];
    }

    public function forCampaign(Campaign $campaign): static
    {
        return $this->state(fn (array $attributes) => [
            'campaign_id' => $campaign->id,
        ]);
    }

    public function forDevice(Device $device): static
    {
        return $this->state(fn (array $attributes) => [
            'device_id' => $device->id,
        ]);
    }

    public function forDate(string $date): static
    {
        return $this->state(fn (array $attributes) => [
            'date' => $date,
        ]);
    }
}
