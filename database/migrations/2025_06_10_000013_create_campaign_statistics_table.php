<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_statistics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->unsignedInteger('impressions')->default(0);
            $table->unsignedInteger('plays')->default(0);
            $table->float('duration')->default(0);
            $table->date('date');
            $table->timestamps();

            $table->index('campaign_id');
            $table->index('device_id');
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_statistics');
    }
};
