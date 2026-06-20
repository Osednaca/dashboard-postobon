<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        $actions = [
            'create', 'update', 'delete', 'login', 'logout', 'sync', 'export',
            'import', 'activate', 'deactivate', 'assign', 'unassign', 'publish',
            'unpublish', 'schedule', 'execute', 'approve', 'reject', 'restart',
        ];

        $entityTypes = [
            'User', 'Device', 'Campaign', 'Media', 'Group', 'Location',
            'Schedule', 'Subscription', 'Notification', 'Setting',
        ];

        $action = fake()->randomElement($actions);
        $entityType = fake()->randomElement($entityTypes);
        $entityId = fake()->numberBetween(1, 500);

        $detailsTemplates = [
            'create' => ['fields' => ['name', 'status'], 'old' => null, 'new' => ['name' => fake()->words(2, true), 'status' => 'active']],
            'update' => ['fields' => ['name', 'status'], 'old' => ['name' => fake()->words(2, true), 'status' => 'inactive'], 'new' => ['name' => fake()->words(2, true), 'status' => 'active']],
            'delete' => ['reason' => fake()->optional()->sentence(), 'soft' => true],
            'login' => ['ip' => fake()->ipv4(), 'method' => 'password'],
            'logout' => ['reason' => 'user_initiated'],
            'sync' => ['source' => 'Z2 API', 'items_synced' => fake()->numberBetween(1, 100)],
            'export' => ['format' => fake()->randomElement(['csv', 'pdf', 'xlsx']), 'records' => fake()->numberBetween(10, 1000)],
            'assign' => ['assigned_to' => fake()->numberBetween(1, 50)],
            'schedule' => ['scheduled_at' => fake()->dateTime()->format('Y-m-d H:i:s')],
        ];

        $details = $detailsTemplates[$action] ?? ['message' => fake()->sentence()];

        return [
            'user_id' => fake()->randomElement([null, User::factory()]),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'details' => $details,
            'ip_address' => fake()->randomElement([null, fake()->ipv4(), fake()->ipv6()]),
            'user_agent' => fake()->randomElement([null, fake()->userAgent()]),
        ];
    }

    public function byUser(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => User::factory(),
        ]);
    }

    public function bySystem(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
        ]);
    }
}
