<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_onboardings', function (Blueprint $table): void {
            $table->string('cloud_domain')->nullable();
            // Only the validated public profile, never REST/cloud credentials.
            $table->json('provisioning_profile')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('device_onboardings', function (Blueprint $table): void {
            $table->dropColumn(['cloud_domain', 'provisioning_profile']);
        });
    }
};
