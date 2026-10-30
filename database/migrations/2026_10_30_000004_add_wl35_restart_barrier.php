<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_device_playbacks', function (Blueprint $table): void {
            $table->string('wl35_wait_boot_id', 80)->nullable();
            $table->timestamp('wl35_wait_started_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('campaign_device_playbacks', function (Blueprint $table): void {
            $table->dropColumn(['wl35_wait_boot_id', 'wl35_wait_started_at']);
        });
    }
};
