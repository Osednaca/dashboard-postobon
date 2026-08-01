<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Deduplicate groups table
        $duplicateGroupNames = DB::table('groups')
            ->select('name', DB::raw('COUNT(*) as total'))
            ->groupBy('name')
            ->having('total', '>', 1)
            ->pluck('name');

        foreach ($duplicateGroupNames as $name) {
            $groupRecords = DB::table('groups')
                ->where('name', $name)
                ->orderByRaw('deleted_at IS NULL DESC')
                ->orderByRaw('z2_group_id IS NOT NULL DESC')
                ->orderBy('id', 'asc')
                ->get();

            $primaryGroup = $groupRecords->first();
            $duplicateIds = $groupRecords->slice(1)->pluck('id')->all();

            if (! empty($duplicateIds)) {
                DB::table('devices')
                    ->whereIn('group_id', $duplicateIds)
                    ->update(['group_id' => $primaryGroup->id]);

                DB::table('schedules')
                    ->whereIn('group_id', $duplicateIds)
                    ->update(['group_id' => $primaryGroup->id]);

                DB::table('groups')->whereIn('id', $duplicateIds)->delete();
            }
        }

        // Add unique constraint to groups.name
        Schema::table('groups', function (Blueprint $table) {
            $table->unique('name');
        });

        // 2. Deduplicate media table
        $duplicateFilePaths = DB::table('media')
            ->select('file_path', DB::raw('COUNT(*) as total'))
            ->groupBy('file_path')
            ->having('total', '>', 1)
            ->pluck('file_path');

        foreach ($duplicateFilePaths as $path) {
            $mediaRecords = DB::table('media')
                ->where('file_path', $path)
                ->orderByRaw('deleted_at IS NULL DESC')
                ->orderBy('id', 'asc')
                ->get();

            $primaryMedia = $mediaRecords->first();
            $duplicateIds = $mediaRecords->slice(1)->pluck('id')->all();

            if (! empty($duplicateIds)) {
                DB::table('schedules')
                    ->whereIn('content_id', $duplicateIds)
                    ->update(['content_id' => $primaryMedia->id]);

                DB::table('campaign_media')
                    ->whereIn('media_id', $duplicateIds)
                    ->update(['media_id' => $primaryMedia->id]);

                DB::table('media')->whereIn('id', $duplicateIds)->delete();
            }
        }

        // Add unique constraint to media.file_path
        Schema::table('media', function (Blueprint $table) {
            $table->unique('file_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });

        Schema::table('media', function (Blueprint $table) {
            $table->dropUnique(['file_path']);
        });
    }
};
