<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            LocationSeeder::class,
            GroupSeeder::class,
            DeviceSeeder::class,
            MediaSeeder::class,
            CampaignSeeder::class,
            ScheduleSeeder::class,
            SubscriptionSeeder::class,
            NotificationSeeder::class,
            AuditLogSeeder::class,
            DeviceHeartbeatSeeder::class,
            CampaignStatisticSeeder::class,
        ]);
    }
}
