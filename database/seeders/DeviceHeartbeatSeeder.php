<?php

namespace Database\Seeders;

use App\Models\Device;
use App\Models\DeviceHeartbeat;
use Illuminate\Database\Seeder;

class DeviceHeartbeatSeeder extends Seeder
{
    public function run(): void
    {
        $devices = Device::all();

        DeviceHeartbeat::factory()
            ->count(200)
            ->recycle($devices)
            ->create();
    }
}
