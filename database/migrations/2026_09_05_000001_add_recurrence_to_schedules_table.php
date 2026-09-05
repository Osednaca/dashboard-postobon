<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            // The original enum prevented adding new commands such as format_sd.
            $table->string('type', 50)->change();
            $table->string('recurrence_type', 20)->default('once')->after('status')->index();
            $table->time('recurrence_time')->nullable()->after('recurrence_type');
            $table->json('recurrence_days')->nullable()->after('recurrence_time');
            $table->unsignedTinyInteger('recurrence_day')->nullable()->after('recurrence_days');
            $table->timestamp('recurrence_ends_at')->nullable()->after('recurrence_day');
            $table->string('last_run_status', 20)->nullable()->after('recurrence_ends_at');
            $table->text('last_error')->nullable()->after('last_run_status');
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropIndex(['recurrence_type']);
            $table->dropColumn([
                'recurrence_type',
                'recurrence_time',
                'recurrence_days',
                'recurrence_day',
                'recurrence_ends_at',
                'last_run_status',
                'last_error',
            ]);
        });
    }
};
