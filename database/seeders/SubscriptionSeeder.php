<?php

namespace Database\Seeders;

use App\Models\Subscription;
use Illuminate\Database\Seeder;

class SubscriptionSeeder extends Seeder
{
    public function run(): void
    {
        Subscription::factory()->count(2)->active()->create();
        Subscription::factory()->count(2)->suspended()->create();
        Subscription::factory()->count(1)->expired()->create();
    }
}
