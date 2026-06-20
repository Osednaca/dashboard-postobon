<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CheckSubscriptions extends Command
{
    protected $signature = 'subscriptions:check';

    protected $description = 'Check expiring subscriptions and send alerts';

    public function handle(): int
    {
        $this->info('Checking subscriptions...');

        $today = Carbon::today();

        $activeSubscriptions = Subscription::where('status', 'active')->get();
        $expiringSoon = [];
        $expiredToday = [];
        $suspended = [];

        foreach ($activeSubscriptions as $subscription) {
            if (! $subscription->end_date) {
                continue;
            }

            $endDate = Carbon::parse($subscription->end_date);
            $daysUntilExpiry = $today->diffInDays($endDate, false);
            $alertDays = $subscription->alert_days_before ?? 7;

            if ($daysUntilExpiry < 0) {
                // Already expired
                $subscription->status = 'expired';
                $subscription->save();
                $expiredToday[] = $subscription;

                $this->warn("Subscription {$subscription->name} expired on {$endDate->toDateString()}.");
            } elseif ($daysUntilExpiry <= $alertDays) {
                $expiringSoon[] = $subscription;
                $this->warn("Subscription {$subscription->name} expires in {$daysUntilExpiry} days ({$endDate->toDateString()}).");
            }
        }

        // Check suspended subscriptions
        $suspendedSubscriptions = Subscription::where('status', 'suspended')->get();
        foreach ($suspendedSubscriptions as $subscription) {
            $suspended[] = $subscription;
            $this->info("Subscription {$subscription->name} is currently suspended.");
        }

        // Send notifications for expiring subscriptions
        $adminUsers = \App\Models\User::where('role', 'admin')->get();
        foreach ($expiringSoon as $subscription) {
            $daysUntilExpiry = $today->diffInDays(Carbon::parse($subscription->end_date), false);

            foreach ($adminUsers as $admin) {
                Notification::create([
                    'user_id' => $admin->id,
                    'type' => 'system',
                    'title' => 'Subscription Expiring Soon',
                    'message' => "Subscription {$subscription->name} expires in {$daysUntilExpiry} days.",
                    'data' => [
                        'subscription_id' => $subscription->id,
                        'subscription_name' => $subscription->name,
                        'end_date' => $subscription->end_date?->toIso8601String(),
                        'days_remaining' => $daysUntilExpiry,
                    ],
                ]);
            }
        }

        // Send notifications for expired subscriptions
        foreach ($expiredToday as $subscription) {
            foreach ($adminUsers as $admin) {
                Notification::create([
                    'user_id' => $admin->id,
                    'type' => 'system',
                    'title' => 'Subscription Expired',
                    'message' => "Subscription {$subscription->name} has expired.",
                    'data' => [
                        'subscription_id' => $subscription->id,
                        'subscription_name' => $subscription->name,
                        'end_date' => $subscription->end_date?->toIso8601String(),
                    ],
                ]);
            }
        }

        $summary = [
            'total_checked' => $activeSubscriptions->count(),
            'expiring_soon' => count($expiringSoon),
            'expired_today' => count($expiredToday),
            'suspended' => count($suspended),
        ];

        Log::info('Subscription check completed', $summary);
        $this->info("Subscription check completed. Expiring soon: {$summary['expiring_soon']}, Expired today: {$summary['expired_today']}, Suspended: {$summary['suspended']}.");

        return self::SUCCESS;
    }
}
