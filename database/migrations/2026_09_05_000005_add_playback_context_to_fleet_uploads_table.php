<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fleet_uploads', function (Blueprint $table) {
            $table->foreignId('source_media_id')
                ->nullable()
                ->after('user_id')
                ->constrained('media')
                ->nullOnDelete();
            $table->boolean('play_after_upload')->default(false)->after('targets');
        });
    }

    public function down(): void
    {
        Schema::table('fleet_uploads', function (Blueprint $table) {
            $table->dropForeign(['source_media_id']);
            $table->dropColumn(['source_media_id', 'play_after_upload']);
        });
    }
};
