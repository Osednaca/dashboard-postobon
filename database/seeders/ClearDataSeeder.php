<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClearDataSeeder extends Seeder
{
    /**
     * Run the database seeds to clear all seeded domain data except users.
     */
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();

        $tables = [
            'campaign_statistics',
            'device_heartbeats',
            'audit_logs',
            'notifications',
            'subscriptions',
            'schedules',
            'device_campaign',
            'campaign_media',
            'campaigns',
            'media',
            'devices',
            'groups',
            'locations',
            'api_logs',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
            }
        }

        Schema::enableForeignKeyConstraints();
    }
}
