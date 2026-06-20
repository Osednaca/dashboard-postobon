<?php

namespace App\Jobs;

use App\Events\SubscriptionExpiring;
use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckExpiringSubscriptionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $timeout = 120;

    public function __construct()
    {
        $this->afterCommit();
    }

    public function handle(): void
    {
        $subscriptions = Subscription::where('status', 'active')->get();

        foreach ($subscriptions as $subscription) {
            $daysRemaining = now()->diffInDays($subscription->end_date, false);

            if ($daysRemaining <= $subscription->alert_days_before && $daysRemaining >= 0) {
                event(new SubscriptionExpiring($subscription, (int) $daysRemaining));
            }

            if ($daysRemaining < 0) {
                $subscription->update(['status' => 'expired']);
                Log::info("Suscripción {$subscription->name} marcada como expirada.");
            }
        }

        Log::info('Verificación de suscripciones por vencer completada.');
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('CheckExpiringSubscriptionsJob falló: ' . $exception->getMessage());
    }
}
