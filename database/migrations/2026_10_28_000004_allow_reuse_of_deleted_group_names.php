<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['sqlite', 'pgsql'], true)) {
            DB::statement('CREATE UNIQUE INDEX groups_active_name_unique ON groups (name) WHERE deleted_at IS NULL');
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            // NULL values are not considered equal in a MySQL unique index.
            Schema::table('groups', function (Blueprint $table): void {
                $table->string('active_name')->nullable()->storedAs('CASE WHEN deleted_at IS NULL THEN name ELSE NULL END');
                $table->unique('active_name', 'groups_active_name_unique');
            });
        } else {
            throw new RuntimeException('Unsupported database driver for active group name uniqueness: '.$driver);
        }

        Schema::table('groups', function (Blueprint $table): void {
            $table->dropUnique('groups_name_unique');
        });
    }

    public function down(): void
    {
        // Reinstating the old constraint must never discard group history.
        if (DB::table('groups')->select('name')->groupBy('name')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Cannot restore global group name uniqueness while duplicate historical names exist.');
        }

        Schema::table('groups', function (Blueprint $table): void {
            $table->unique('name', 'groups_name_unique');
        });

        if (in_array(DB::connection()->getDriverName(), ['sqlite', 'pgsql'], true)) {
            DB::statement('DROP INDEX groups_active_name_unique');
        } else {
            Schema::table('groups', function (Blueprint $table): void {
                $table->dropUnique('groups_active_name_unique');
                $table->dropColumn('active_name');
            });
        }
    }
};
