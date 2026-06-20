<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'Admin User',
            'email' => 'admin@3dfan.local',
        ]);

        User::factory()->operator()->create([
            'name' => 'Operator One',
            'email' => 'operator1@3dfan.local',
        ]);

        User::factory()->operator()->create([
            'name' => 'Operator Two',
            'email' => 'operator2@3dfan.local',
        ]);
    }
}
