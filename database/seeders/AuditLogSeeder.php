<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Seeder;

class AuditLogSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();

        AuditLog::factory()
            ->count(100)
            ->recycle($users)
            ->create();
    }
}
