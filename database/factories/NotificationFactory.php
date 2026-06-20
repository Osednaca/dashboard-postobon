<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        $types = ['system', 'email'];
        $type = fake()->randomElement($types);

        $systemTitles = [
            'Device Offline', 'Device Online', 'Campaign Started', 'Campaign Finished',
            'Firmware Update Available', 'Heartbeat Missed', 'Schedule Executed',
            'Storage Warning', 'Subscription Expiring', 'New Device Connected',
            'Maintenance Required', 'Sync Complete', 'Report Ready', 'Alert Triggered',
        ];

        $emailTitles = [
            'Weekly Report', 'Monthly Analytics', 'Subscription Renewal', 'Welcome',
            'Password Reset', 'Campaign Summary', 'Device Status Report', 'Invoice Ready',
        ];

        $titles = $type === 'system' ? $systemTitles : $emailTitles;
        $title = fake()->randomElement($titles);

        $messages = [
            'Device Offline' => 'Device {device} has been offline for {minutes} minutes.',
            'Device Online' => 'Device {device} is back online.',
            'Campaign Started' => 'Campaign "{campaign}" has started successfully.',
            'Campaign Finished' => 'Campaign "{campaign}" has finished.',
            'Firmware Update Available' => 'New firmware version {version} is available for device {device}.',
            'Heartbeat Missed' => 'Missed heartbeat from device {device}.',
            'Schedule Executed' => 'Schedule "{schedule}" was executed successfully.',
            'Storage Warning' => 'Storage usage is at {percentage}%.',
            'Subscription Expiring' => 'Your subscription expires in {days} days.',
            'New Device Connected' => 'A new device {device} has been connected.',
            'Maintenance Required' => 'Device {device} requires maintenance.',
            'Sync Complete' => 'Synchronization completed. {count} items processed.',
            'Report Ready' => 'Your {report} report is ready for download.',
            'Alert Triggered' => 'Alert "{alert}" has been triggered on device {device}.',
            'Weekly Report' => 'Your weekly report for week {week} is now available.',
            'Monthly Analytics' => 'Monthly analytics for {month} are ready.',
            'Subscription Renewal' => 'Please renew your subscription before {date}.',
            'Welcome' => 'Welcome to 3D Fan Dashboard!',
            'Password Reset' => 'Click here to reset your password.',
            'Campaign Summary' => 'Summary for campaign "{campaign}" is attached.',
            'Device Status Report' => 'Device status report for {date} is ready.',
            'Invoice Ready' => 'Invoice #{invoice} is ready for payment.',
        ];

        $messageTemplate = $messages[$title] ?? fake()->sentence(10);
        $message = str_replace(
            ['{device}', '{campaign}', '{schedule}', '{version}', '{minutes}', '{percentage}', '{days}', '{count}', '{report}', '{alert}', '{week}', '{month}', '{date}', '{invoice}'],
            [
                'Fan-' . fake()->randomNumber(4),
                fake()->catchPhrase(),
                'Task-' . fake()->randomNumber(3),
                fake()->randomElement(['1.0', '2.0', '3.0']),
                fake()->numberBetween(5, 120),
                fake()->numberBetween(80, 99),
                fake()->numberBetween(1, 30),
                fake()->numberBetween(10, 500),
                fake()->randomElement(['weekly', 'monthly', 'quarterly']),
                fake()->word(),
                fake()->numberBetween(1, 52),
                fake()->month(),
                fake()->date(),
                fake()->randomNumber(6),
            ],
            $messageTemplate
        );

        $data = [
            'device_id' => fake()->randomNumber(3),
            'campaign_id' => fake()->randomNumber(3),
            'priority' => fake()->randomElement(['low', 'medium', 'high']),
            'source' => fake()->randomElement(['system', 'user', 'api']),
        ];

        $readAt = fake()->boolean(70) ? fake()->dateTimeBetween('-7 days', 'now') : null;

        return [
            'user_id' => User::factory(),
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'read_at' => $readAt,
        ];
    }

    public function unread(): static
    {
        return $this->state(fn (array $attributes) => [
            'read_at' => null,
        ]);
    }

    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'read_at' => fake()->dateTimeBetween('-7 days', 'now'),
        ]);
    }

    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'system',
        ]);
    }

    public function email(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'email',
        ]);
    }
}
