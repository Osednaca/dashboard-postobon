<?php

namespace App\Listeners;

use App\Events\SubscriptionExpiring;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;

class SendSubscriptionAlert
{
    public function handle(SubscriptionExpiring $event): void
    {
        $subscription = $event->subscription;

        Notification::create([
            'user_id' => null,
            'type' => 'subscription_expiring',
            'title' => 'Suscripción por vencer',
            'message' => "La suscripción {$subscription->name} vencerá en {$event->daysRemaining} días.",
            'data' => [
                'subscription_id' => $subscription->id,
                'subscription_name' => $subscription->name,
                'days_remaining' => $event->daysRemaining,
                'end_date' => $subscription->end_date->format('Y-m-d'),
            ],
        ]);

        Log::info("Suscripción {$subscription->name} vencerá en {$event->daysRemaining} días.");
    }
}
