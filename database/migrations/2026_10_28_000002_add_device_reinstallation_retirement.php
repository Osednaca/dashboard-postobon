<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table): void {
            $table->timestamp('retired_for_onboarding_at')->nullable();
        });
        Schema::create('device_retirement_exclusions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->string('scope', 32);
            $table->string('resource_id', 64);
            $table->unique(['device_id', 'scope', 'resource_id']);
            $table->index(['scope', 'resource_id']);
        });
    }

    public function down(): void
    {
        if (DB::table('devices')->whereNotNull('retired_for_onboarding_at')->exists()
            || DB::table('device_retirement_exclusions')->exists()) {
            throw new RuntimeException('Cannot roll back device retirement while retirement records exist.');
        }
        Schema::dropIfExists('device_retirement_exclusions');
        Schema::table('devices', fn (Blueprint $table) => $table->dropColumn('retired_for_onboarding_at'));
    }
};
