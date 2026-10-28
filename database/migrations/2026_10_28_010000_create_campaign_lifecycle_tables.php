<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', fn (Blueprint $t) => $t->text('lifecycle_error')->nullable());
        Schema::table('wl35_device_media', fn (Blueprint $t) => $t->uuid('upload_identity')->nullable());
        Schema::create('campaign_device_files', function (Blueprint $t): void {
            $t->id();
            $t->string('target_key');
            $t->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();
            $t->string('identity_key', 64);
            $t->string('filename')->nullable();
            $t->unsignedInteger('video_index')->nullable();
            $t->string('state')->default('pending');
            $t->boolean('owned')->default(false);
            $t->text('last_error')->nullable();
            $t->json('evidence')->nullable();
            $t->timestamp('requested_at')->nullable();
            $t->timestamp('confirmed_at')->nullable();
            $t->timestamps();
            $t->unique(['target_key', 'identity_key'], 'campaign_file_identity');
        });
        Schema::create('campaign_deliveries', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $t->foreignId('campaign_device_file_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('position');
            $t->unsignedInteger('duration');
            $t->timestamp('released_at')->nullable();
            $t->timestamps();
            $t->unique(['campaign_id', 'campaign_device_file_id'], 'campaign_delivery_unique');
        });
        Schema::create('campaign_device_playbacks', function (Blueprint $t): void {
            $t->id();
            $t->string('target_key')->unique();
            $t->unsignedBigInteger('campaign_id')->nullable();
            $t->unsignedBigInteger('delivery_id')->nullable();
            $t->timestamp('due_at')->nullable();
            $t->boolean('powered_off')->default(false);
            $t->text('last_error')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', fn (Blueprint $t) => $t->dropColumn('lifecycle_error'));
        Schema::table('wl35_device_media', fn (Blueprint $t) => $t->dropColumn('upload_identity'));
        Schema::dropIfExists('campaign_device_playbacks');
        Schema::dropIfExists('campaign_deliveries');
        Schema::dropIfExists('campaign_device_files');
    }
};
