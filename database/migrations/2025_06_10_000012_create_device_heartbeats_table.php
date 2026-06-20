<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_heartbeats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->integer('rpm')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('received_at');
            $table->timestamps();

            $table->index('device_id');
            $table->index('received_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_heartbeats');
    }
};
