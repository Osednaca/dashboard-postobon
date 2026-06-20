<?php

namespace App\DTOs;

use Illuminate\Http\Request;

class NotificationDTO
{
    /**
     * @param int|null $userId
     * @param string|null $type
     * @param string|null $title
     * @param string|null $message
     * @param array<string, mixed>|null $data
     * @param string|null $readAt
     */
    public function __construct(
        public readonly ?int $userId = null,
        public readonly ?string $type = null,
        public readonly ?string $title = null,
        public readonly ?string $message = null,
        public readonly ?array $data = null,
        public readonly ?string $readAt = null,
    ) {
    }

    /**
     * Create a NotificationDTO from a Request.
     *
     * @param Request $request
     * @return self
     */
    public static function fromRequest(Request $request): self
    {
        return new self(
            userId: $request->input('user_id') !== null ? (int) $request->input('user_id') : null,
            type: $request->input('type'),
            title: $request->input('title'),
            message: $request->input('message'),
            data: $request->input('data'),
            readAt: $request->input('read_at'),
        );
    }

    /**
     * Convert the DTO to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'user_id' => $this->userId,
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,
            'data' => $this->data,
            'read_at' => $this->readAt,
        ], fn ($value) => $value !== null);
    }
}
