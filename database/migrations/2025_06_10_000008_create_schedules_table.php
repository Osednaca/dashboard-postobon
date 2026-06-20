<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['power_on', 'power_off', 'change_content', 'activate_campaign']);
            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $table->timestamp('scheduled_at');
            $table->timestamp('executed_at')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();

            $table->index('type');
            $table->index('device_id');
            $table->index('group_id');
            $table->index('campaign_id');
            $table->index('scheduled_at');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
