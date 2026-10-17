<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->string('target_scope', 16)->nullable();
        });

        // Null targets may mean a deleted selection; never infer a global instruction.
        DB::table('schedules')->whereNotNull('device_id')->whereNull('group_id')
            ->update(['target_scope' => 'device']);
        DB::table('schedules')->whereNotNull('group_id')->whereNull('device_id')
            ->update(['target_scope' => 'group']);
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn('target_scope');
        });
    }
};
