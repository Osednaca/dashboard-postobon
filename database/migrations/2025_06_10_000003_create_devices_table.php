<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('mac_address')->unique();
            $table->string('firmware')->nullable();
            $table->string('hardware')->nullable();
            $table->integer('rpm')->nullable();
            $table->enum('status', ['online', 'offline', 'disabled', 'maintenance'])->default('offline');
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->float('working_hours')->default(0);
            $table->enum('power_status', ['on', 'off'])->default('off');
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('location_id');
            $table->index('group_id');
            $table->index('last_heartbeat_at');
            $table->index('power_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
