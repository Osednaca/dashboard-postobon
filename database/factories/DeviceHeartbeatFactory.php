<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\DeviceHeartbeat;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeviceHeartbeatFactory extends Factory
{
    protected $model = DeviceHeartbeat::class;

    public function definition(): array
    {
        $statuses = ['online', 'offline', 'degraded', 'error', 'syncing'];
        $status = fake()->randomElement($statuses);
        $rpm = null;

        if ($status === 'online' || $status === 'degraded') {
            $rpm = fake()->numberBetween(100, 1500);
        } elseif ($status === 'error') {
            $rpm = fake()->randomElement([0, fake()->numberBetween(50, 200)]);
        }

        return [
            'device_id' => Device::factory(),
            'rpm' => $rpm,
            'status' => $status,
            'received_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }

    public function online(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'online',
            'rpm' => fake()->numberBetween(300, 1200),
        ]);
    }

    public function offline(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'offline',
            'rpm' => 0,
        ]);
    }

    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'received_at' => fake()->dateTimeBetween('-1 hour', 'now'),
        ]);
    }

    public function old(): static
    {
        return $this->state(fn (array $attributes) => [
            'received_at' => fake()->dateTimeBetween('-30 days', '-1 day'),
        ]);
    }
}
