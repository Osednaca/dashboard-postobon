<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->roles(['superadmin', 'admin', 'tecnico', 'campanas']);
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'campanas')->update(['role' => 'admin']);
        $this->roles(['superadmin', 'admin', 'tecnico']);
    }

    private function roles(array $roles): void
    {
        Schema::table('users', function (Blueprint $table) use ($roles): void {
            $table->enum('role', $roles)->default('tecnico')->change();
        });
    }
};
