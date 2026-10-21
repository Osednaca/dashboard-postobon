<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_onboardings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('establishment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('device_type', 10)->default('z2');
            $table->string('expected_identifier', 12)->index();
            // Unique while an attempt is active; released at its terminal state.
            $table->string('active_identifier', 12)->nullable()->unique();
            $table->string('name', 64);
            $table->unsignedTinyInteger('play_mode');
            $table->unsignedTinyInteger('sync_class');
            $table->string('status', 24)->default('prepared');
            $table->string('idempotency_key', 100);
            $table->char('request_hash', 64);
            $table->unsignedBigInteger('baseline_heartbeat_count')->default(0);
            $table->timestamp('baseline_last_seen_at')->nullable();
            $table->timestamp('configuration_reported_at')->nullable();
            $table->timestamp('verification_baseline_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('completed_at')->nullable();
            $table->string('last_result_code', 32)->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_onboardings');
    }
};
