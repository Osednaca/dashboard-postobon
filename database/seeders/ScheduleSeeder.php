<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\Device;
use App\Models\Group;
use App\Models\Schedule;
use Illuminate\Database\Seeder;

class ScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $devices = Device::all();
        $groups = Group::all();
        $campaigns = Campaign::all();

        Schedule::factory()
            ->count(30)
            ->recycle($devices)
            ->recycle($groups)
            ->recycle($campaigns)
            ->create();
    }
}
