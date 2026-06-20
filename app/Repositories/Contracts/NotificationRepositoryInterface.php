<?php

namespace App\Repositories\Contracts;

use App\Models\Notification;
use Illuminate\Database\Eloquent\Collection;

interface NotificationRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Get unread notifications for a user.
     *
     * @param int|string $userId
     * @return Collection<int, Notification>
     */
    public function getUnreadForUser(int|string $userId): Collection;

    /**
     * Mark notifications as read for a user.
     *
     * @param int|string $userId
     * @return int
     */
    public function markAsRead(int|string $userId): int;
}
