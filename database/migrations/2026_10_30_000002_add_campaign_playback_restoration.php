<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_device_playbacks', function (Blueprint $table): void {
            $table->json('baseline')->nullable();
            $table->string('restore_state')->nullable();
            $table->text('restore_error')->nullable();
            $table->timestamp('baseline_closed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('campaign_device_playbacks', fn (Blueprint $table) => $table->dropColumn(['baseline', 'restore_state', 'restore_error', 'baseline_closed_at']));
    }
};
