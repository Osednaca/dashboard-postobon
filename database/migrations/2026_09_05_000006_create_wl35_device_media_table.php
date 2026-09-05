<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wl35_device_media', function (Blueprint $table) {
            $table->id();
            $table->string('device_id', 160);
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->unsignedSmallInteger('video_index');
            $table->timestamps();

            $table->unique(['device_id', 'media_id']);
            $table->unique(['device_id', 'video_index']);
            $table->index('device_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wl35_device_media');
    }
};
