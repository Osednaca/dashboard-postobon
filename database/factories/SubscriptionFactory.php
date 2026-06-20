<?php

namespace Database\Factories;

use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        $statuses = ['active', 'suspended', 'expired'];
        $status = fake()->randomElement($statuses);
        $startDate = null;
        $endDate = null;

        if ($status === 'active') {
            $startDate = fake()->dateTimeBetween('-180 days', '-1 day');
            $endDate = fake()->dateTimeBetween('+30 days', '+365 days');
        } elseif ($status === 'suspended') {
            $startDate = fake()->dateTimeBetween('-180 days', '-90 days');
            $endDate = fake()->dateTimeBetween('+30 days', '+180 days');
        } elseif ($status === 'expired') {
            $startDate = fake()->dateTimeBetween('-365 days', '-180 days');
            $endDate = fake()->dateTimeBetween('-90 days', '-1 day');
        }

        $plans = [
            'Basic Monthly', 'Basic Annual', 'Professional Monthly', 'Professional Annual',
            'Enterprise Monthly', 'Enterprise Annual', 'Starter', 'Growth', 'Scale',
        ];

        $restrictions = [
            'max_devices' => fake()->randomElement([10, 25, 50, 100, 250, 500]),
            'max_campaigns' => fake()->randomElement([5, 10, 20, 50, 100]),
            'max_storage_gb' => fake()->randomElement([10, 50, 100, 250, 500]),
            'support_level' => fake()->randomElement(['email', 'chat', 'phone', 'dedicated']),
            'analytics' => fake()->boolean(),
        ];

        return [
            'name' => fake()->randomElement($plans),
            'status' => $status,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'alert_days_before' => fake()->randomElement([3, 7, 14, 30]),
            'restrictions' => $restrictions,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'start_date' => fake()->dateTimeBetween('-180 days', '-1 day'),
            'end_date' => fake()->dateTimeBetween('+30 days', '+365 days'),
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'suspended',
            'start_date' => fake()->dateTimeBetween('-180 days', '-90 days'),
            'end_date' => fake()->dateTimeBetween('+30 days', '+180 days'),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'expired',
            'start_date' => fake()->dateTimeBetween('-365 days', '-180 days'),
            'end_date' => fake()->dateTimeBetween('-90 days', '-1 day'),
        ]);
    }
}
