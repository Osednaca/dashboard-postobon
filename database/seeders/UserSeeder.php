<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->superadmin()->create([
            'name' => 'Superadministrador',
            'email' => 'admin@3dfan.local',
        ]);

        User::factory()->tecnico()->create([
            'name' => 'Técnico Uno',
            'email' => 'operator1@3dfan.local',
        ]);

        User::factory()->tecnico()->create([
            'name' => 'Técnico Dos',
            'email' => 'operator2@3dfan.local',
        ]);
    }
}
