<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\Group;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeviceFactory extends Factory
{
    protected $model = Device::class;

    public function definition(): array
    {
        $mac = implode(':', array_map(
            fn () => strtoupper(fake()->randomElement(['00', '1A', '2B', '3C', '4D', '5E', '6F', '7A', '8B', '9C', 'A1', 'B2', 'C3', 'D4', 'E5', 'F6'])),
            range(1, 6)
        ));

        $firmwareVersions = ['1.0.0', '1.2.3', '2.0.1', '2.1.0', '2.4.5', '3.0.0-beta', '3.1.2', '4.0.0'];
        $hardwareVersions = ['Z2-100', 'Z2-200', 'Z2-300', 'Z2-400', 'Z2-500', 'Z2-Pro', 'Z2-Lite', 'Z2-Mini'];
        $statuses = ['online', 'offline', 'disabled', 'maintenance'];
        $powerStatuses = ['on', 'off'];

        return [
            'name' => '3D Fan ' . fake()->randomElement(['Indoor', 'Outdoor', 'Pro', 'Mini', 'Elite', 'Standard']) . ' ' . fake()->randomNumber(4),
            'mac_address' => $mac,
            'firmware' => fake()->randomElement($firmwareVersions),
            'hardware' => fake()->randomElement($hardwareVersions),
            'rpm' => fake()->randomElement([null, 100, 200, 300, 400, 500, 600, 800, 1000, 1200, 1500]),
            'status' => fake()->randomElement($statuses),
            'location_id' => null,
            'group_id' => null,
            'last_heartbeat_at' => fake()->randomElement([null, fake()->dateTimeBetween('-7 days', 'now')]),
            'working_hours' => fake()->randomFloat(2, 0, 8760),
            'power_status' => fake()->randomElement($powerStatuses),
        ];
    }

    public function online(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'online',
            'last_heartbeat_at' => now(),
        ]);
    }

    public function offline(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'offline',
            'last_heartbeat_at' => fake()->dateTimeBetween('-30 days', '-8 days'),
        ]);
    }

    public function withLocation(): static
    {
        return $this->state(fn (array $attributes) => [
            'location_id' => Location::factory(),
        ]);
    }

    public function withGroup(): static
    {
        return $this->state(fn (array $attributes) => [
            'group_id' => Group::factory(),
        ]);
    }
}
