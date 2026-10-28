<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_onboardings', function (Blueprint $table): void {
            $table->unsignedInteger('sync_class')->change();
        });
    }

    public function down(): void
    {
        if (DB::table('device_onboardings')->where('sync_class', '>', 255)->exists()) {
            throw new RuntimeException('Cannot shrink sync_class while larger classes exist; preserve onboarding records.');
        }
        Schema::table('device_onboardings', function (Blueprint $table): void {
            $table->unsignedTinyInteger('sync_class')->change();
        });
    }
};
