<?php

namespace Database\Seeders;

use App\Models\Device;
use App\Models\Group;
use Illuminate\Database\Seeder;

class DeviceSeeder extends Seeder
{
    public function run(): void
    {
        $groups = Group::all();

        Device::factory()
            ->count(50)
            ->recycle($groups)
            ->create();
    }
}
