<?php

namespace App\Services;

use App\Models\Notification;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;

class NotificationService extends BaseService
{
    /**
     * NotificationService constructor.
     *
     * @param NotificationRepositoryInterface $notificationRepository
     */
    public function __construct(NotificationRepositoryInterface $notificationRepository)
    {
        parent::__construct($notificationRepository);
    }

    /**
     * Get unread notifications for a user.
     *
     * @param int|string $userId
     * @return Collection<int, Notification>
     */
    public function getUnreadForUser(int|string $userId): Collection
    {
        return $this->repository->getUnreadForUser($userId);
    }

    /**
     * Mark notifications as read for a user.
     *
     * @param int|string $userId
     * @return int
     */
    public function markAsRead(int|string $userId): int
    {
        return $this->repository->markAsRead($userId);
    }

    /**
     * Send a notification via email.
     *
     * @param int|string $userId
     * @param string $subject
     * @param string $body
     * @return bool
     */
    public function sendEmail(int|string $userId, string $subject, string $body): bool
    {
        try {
            $user = \App\Models\User::find($userId);

            if ($user === null) {
                return false;
            }

            Mail::raw($body, function ($message) use ($user, $subject) {
                $message->to($user->email)
                    ->subject($subject);
            });

            return true;
        } catch (\Exception $e) {
            report($e);
            return false;
        }
    }

    /**
     * Create and send a notification.
     *
     * @param int|string $userId
     * @param string $type
     * @param string $title
     * @param string $message
     * @param array<string, mixed>|null $data
     * @return Notification
     */
    public function notify(int|string $userId, string $type, string $title, string $message, ?array $data = null): Notification
    {
        $notification = $this->repository->create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
        ]);

        /** @var Notification */
        return $notification;
    }
}
