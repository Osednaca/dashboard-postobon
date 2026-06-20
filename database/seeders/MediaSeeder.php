<?php

namespace Database\Seeders;

use App\Models\Media;
use Illuminate\Database\Seeder;

class MediaSeeder extends Seeder
{
    public function run(): void
    {
        Media::factory()->count(20)->video()->create();
        Media::factory()->count(10)->image()->create();
    }
}
