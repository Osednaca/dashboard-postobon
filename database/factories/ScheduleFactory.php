<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\Device;
use App\Models\Group;
use App\Models\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScheduleFactory extends Factory
{
    protected $model = Schedule::class;

    public function definition(): array
    {
        $types = ['power_on', 'power_off', 'change_content', 'activate_campaign'];
        $type = fake()->randomElement($types);

        $statuses = ['pending', 'executed', 'failed', 'cancelled'];
        $status = fake()->randomElement($statuses);
        $scheduledAt = fake()->dateTimeBetween('-7 days', '+7 days');
        $executedAt = null;

        if ($status === 'executed') {
            $executedAt = fake()->dateTimeBetween($scheduledAt, '+2 days');
        } elseif ($status === 'failed') {
            $executedAt = fake()->dateTimeBetween($scheduledAt, '+2 days');
        }

        $deviceId = null;
        $groupId = null;
        $campaignId = null;

        if (fake()->boolean(60)) {
            $deviceId = Device::factory();
        } elseif (fake()->boolean(50)) {
            $groupId = Group::factory();
        }

        if ($type === 'activate_campaign' || fake()->boolean(30)) {
            $campaignId = Campaign::factory();
        }

        return [
            'name' => ucfirst(str_replace('_', ' ', $type)) . ' - ' . fake()->company() . ' ' . fake()->randomNumber(2),
            'type' => $type,
            'device_id' => $deviceId,
            'group_id' => $groupId,
            'campaign_id' => $campaignId,
            'scheduled_at' => $scheduledAt,
            'executed_at' => $executedAt,
            'status' => $status,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'executed_at' => null,
        ]);
    }

    public function executed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'executed',
            'executed_at' => fake()->dateTimeBetween('-7 days', 'now'),
        ]);
    }

    public function forDevice(): static
    {
        return $this->state(fn (array $attributes) => [
            'device_id' => Device::factory(),
            'group_id' => null,
        ]);
    }

    public function forGroup(): static
    {
        return $this->state(fn (array $attributes) => [
            'device_id' => null,
            'group_id' => Group::factory(),
        ]);
    }
}
