<?php

namespace App\DTOs;

use Illuminate\Http\Request;

class DeviceHeartbeatDTO
{
    /**
     * @param int|null $deviceId
     * @param int|null $rpm
     * @param string|null $status
     * @param string|null $receivedAt
     */
    public function __construct(
        public readonly ?int $deviceId = null,
        public readonly ?int $rpm = null,
        public readonly ?string $status = null,
        public readonly ?string $receivedAt = null,
    ) {
    }

    /**
     * Create a DeviceHeartbeatDTO from a Request.
     *
     * @param Request $request
     * @return self
     */
    public static function fromRequest(Request $request): self
    {
        return new self(
            deviceId: $request->input('device_id') !== null ? (int) $request->input('device_id') : null,
            rpm: $request->input('rpm') !== null ? (int) $request->input('rpm') : null,
            status: $request->input('status'),
            receivedAt: $request->input('received_at'),
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
            'device_id' => $this->deviceId,
            'rpm' => $this->rpm,
            'status' => $this->status,
            'received_at' => $this->receivedAt,
        ], fn ($value) => $value !== null);
    }
}
