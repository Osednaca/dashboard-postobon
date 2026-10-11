<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wl35_device_media', function (Blueprint $table): void {
            $table->dropForeign(['media_id']);
            $table->unsignedBigInteger('media_id')->nullable()->change();
            $table->string('filename', 255)->nullable();
            $table->foreign('media_id')->references('id')->on('media')->nullOnDelete();
        });

        foreach (DB::table('wl35_device_media')->join('media', 'media.id', '=', 'wl35_device_media.media_id')
            ->select('wl35_device_media.id', 'media.original_name', 'media.name')->get() as $mapping) {
            DB::table('wl35_device_media')->where('id', $mapping->id)
                ->update(['filename' => $mapping->original_name ?: $mapping->name]);
        }
    }

    public function down(): void
    {
        if (DB::table('wl35_device_media')->whereNull('media_id')->exists()) {
            throw new RuntimeException('El rollback requiere respaldar y resolver las etiquetas WL35 sin medio asociado; no se eliminaron.');
        }

        Schema::table('wl35_device_media', function (Blueprint $table): void {
            $table->dropForeign(['media_id']);
            $table->unsignedBigInteger('media_id')->nullable(false)->change();
            $table->foreign('media_id')->references('id')->on('media')->cascadeOnDelete();
            $table->dropColumn('filename');
        });
    }
};
