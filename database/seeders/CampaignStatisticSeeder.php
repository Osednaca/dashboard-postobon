<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\CampaignStatistic;
use App\Models\Device;
use Illuminate\Database\Seeder;

class CampaignStatisticSeeder extends Seeder
{
    public function run(): void
    {
        $campaigns = Campaign::all();
        $devices = Device::all();

        CampaignStatistic::factory()
            ->count(300)
            ->recycle($campaigns)
            ->recycle($devices)
            ->create();
    }
}
