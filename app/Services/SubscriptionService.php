<?php

namespace App\Services;

use App\Models\Subscription;
use App\Repositories\Contracts\SubscriptionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class SubscriptionService extends BaseService
{
    /**
     * SubscriptionService constructor.
     *
     * @param SubscriptionRepositoryInterface $subscriptionRepository
     */
    public function __construct(SubscriptionRepositoryInterface $subscriptionRepository)
    {
        parent::__construct($subscriptionRepository);
    }

    /**
     * Get subscriptions that are about to expire.
     *
     * @return Collection<int, Subscription>
     */
    public function getExpiringSoon(): Collection
    {
        return $this->repository->all()->filter(function (Subscription $subscription) {
            if ($subscription->end_date === null || $subscription->alert_days_before === null) {
                return false;
            }

            $alertDate = Carbon::parse($subscription->end_date)->subDays($subscription->alert_days_before);

            return Carbon::now()->gte($alertDate) && Carbon::now()->lt($subscription->end_date);
        });
    }

    /**
     * Get expired subscriptions.
     *
     * @return Collection<int, Subscription>
     */
    public function getExpired(): Collection
    {
        return $this->repository->all()->filter(function (Subscription $subscription) {
            return $subscription->end_date !== null && Carbon::now()->gte($subscription->end_date);
        });
    }

    /**
     * Send expiration alerts.
     *
     * @return int
     */
    public function sendExpirationAlerts(): int
    {
        $expiring = $this->getExpiringSoon();
        $count = 0;

        foreach ($expiring as $subscription) {
            // Send alert logic here (e.g., dispatch notification job)
            $count++;
        }

        return $count;
    }
}
