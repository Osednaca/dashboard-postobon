<?php

namespace App\Repositories;

use App\Models\Subscription;
use App\Repositories\Contracts\SubscriptionRepositoryInterface;

class SubscriptionRepository extends BaseRepository implements SubscriptionRepositoryInterface
{
    /**
     * SubscriptionRepository constructor.
     *
     * @param Subscription $subscription
     */
    public function __construct(Subscription $subscription)
    {
        parent::__construct($subscription);
    }
}
