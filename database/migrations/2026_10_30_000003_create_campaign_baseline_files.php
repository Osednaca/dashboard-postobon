<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_baseline_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('playback_id')->constrained('campaign_device_playbacks')->cascadeOnDelete();
            $table->uuid('session_id');
            $table->unsignedInteger('position');
            $table->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('filename')->nullable();
            $table->unsignedInteger('original_index')->nullable();
            $table->uuid('upload_identity')->nullable();
            $table->string('backup_disk');
            $table->string('backup_path');
            $table->char('backup_hash', 64);
            $table->unsignedBigInteger('backup_size');
            $table->string('state')->default('backed_up');
            $table->json('operation')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->unique(['playback_id', 'session_id', 'position'], 'baseline_session_position');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_baseline_files');
    }
};
