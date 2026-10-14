<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->roles(['admin', 'operator', 'superadmin', 'tecnico'], 'operator');
        DB::table('users')->where('role', 'admin')->update(['role' => 'superadmin']);
        DB::table('users')->where('role', 'operator')->update(['role' => 'tecnico']);
        $this->roles(['superadmin', 'admin', 'tecnico'], 'tecnico');
    }

    public function down(): void
    {
        $this->roles(['admin', 'operator', 'superadmin', 'tecnico'], 'operator');
        DB::table('users')->where('role', 'superadmin')->update(['role' => 'admin']);
        DB::table('users')->where('role', 'tecnico')->update(['role' => 'operator']);
        $this->roles(['admin', 'operator'], 'operator');
    }

    private function roles(array $roles, string $default): void
    {
        Schema::table('users', function (Blueprint $table) use ($roles, $default) {
            $table->enum('role', $roles)->default($default)->change();
        });
    }
};
