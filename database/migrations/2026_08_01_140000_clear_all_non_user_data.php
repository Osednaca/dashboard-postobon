<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Truncate operation cannot be undone automatically
    }
};
