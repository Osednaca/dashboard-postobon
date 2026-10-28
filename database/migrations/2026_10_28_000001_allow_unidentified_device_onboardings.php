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
            $table->string('expected_identifier', 12)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Never delete recovery records or invent identities to restore NOT NULL.
        if (DB::table('device_onboardings')->whereNull('expected_identifier')->exists()) {
            throw new RuntimeException('Cannot restore required onboarding identity while unidentified sessions exist. Preserve these recovery records and keep this migration applied.');
        }
        Schema::table('device_onboardings', function (Blueprint $table): void {
            $table->string('expected_identifier', 12)->nullable(false)->change();
        });
    }
};
