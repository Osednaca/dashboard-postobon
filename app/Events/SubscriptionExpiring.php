<?php

namespace App\Events;

use App\Models\Subscription;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionExpiring
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Subscription $subscription;
    public int $daysRemaining;

    public function __construct(Subscription $subscription, int $daysRemaining)
    {
        $this->subscription = $subscription;
        $this->daysRemaining = $daysRemaining;
    }
}
