<?php

namespace App\Listeners;

use App\Events\SubscriptionExpiring;

class SendSubscriptionAlert
{
    public function handle(SubscriptionExpiring $event): void
    {
        // Old queued events must not restart alerts for the retired module.
    }
}
