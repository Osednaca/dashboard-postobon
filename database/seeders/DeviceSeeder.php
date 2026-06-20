<?php

namespace Database\Seeders;

use App\Models\Device;
use App\Models\Group;
use App\Models\Location;
use Illuminate\Database\Seeder;

class DeviceSeeder extends Seeder
{
    public function run(): void
    {
        $locations = Location::all();
        $groups = Group::all();

        Device::factory()
            ->count(50)
            ->recycle($locations)
            ->recycle($groups)
            ->create();
    }
}
