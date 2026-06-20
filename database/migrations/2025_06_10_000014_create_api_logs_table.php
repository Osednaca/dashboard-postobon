<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_logs', function (Blueprint $table) {
            $table->id();
            $table->string('service')->index();
            $table->string('endpoint');
            $table->string('method', 10);
            $table->text('request_body')->nullable();
            $table->text('response_body')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->unsignedTinyInteger('attempt')->default(1);
            $table->boolean('success')->default(false);
            $table->text('error_message')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_logs');
    }
};
