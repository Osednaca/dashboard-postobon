<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            GroupSeeder::class,
            DeviceSeeder::class,
            MediaSeeder::class,
            CampaignSeeder::class,
            ScheduleSeeder::class,
            NotificationSeeder::class,
            AuditLogSeeder::class,
            DeviceHeartbeatSeeder::class,
            CampaignStatisticSeeder::class,
        ]);
    }
}
