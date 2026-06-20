<?php

namespace App\Repositories;

use App\Models\Notification;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class NotificationRepository extends BaseRepository implements NotificationRepositoryInterface
{
    /**
     * NotificationRepository constructor.
     *
     * @param Notification $notification
     */
    public function __construct(Notification $notification)
    {
        parent::__construct($notification);
    }

    /**
     * @inheritDoc
     */
    public function getUnreadForUser(int|string $userId): Collection
    {
        return $this->model
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->get();
    }

    /**
     * @inheritDoc
     */
    public function markAsRead(int|string $userId): int
    {
        return $this->model
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => Carbon::now()]);
    }
}
