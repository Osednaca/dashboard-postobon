<?php

namespace App\DTOs;

use Illuminate\Http\Request;

class ScheduleDTO
{
    /**
     * @param string|null $name
     * @param string|null $type
     * @param int|null $deviceId
     * @param int|null $groupId
     * @param int|null $campaignId
     * @param string|null $scheduledAt
     * @param string|null $executedAt
     * @param string|null $status
     */
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $type = null,
        public readonly ?int $deviceId = null,
        public readonly ?int $groupId = null,
        public readonly ?int $campaignId = null,
        public readonly ?string $scheduledAt = null,
        public readonly ?string $executedAt = null,
        public readonly ?string $status = null,
    ) {
    }

    /**
     * Create a ScheduleDTO from a Request.
     *
     * @param Request $request
     * @return self
     */
    public static function fromRequest(Request $request): self
    {
        return new self(
            name: $request->input('name'),
            type: $request->input('type'),
            deviceId: $request->input('device_id') !== null ? (int) $request->input('device_id') : null,
            groupId: $request->input('group_id') !== null ? (int) $request->input('group_id') : null,
            campaignId: $request->input('campaign_id') !== null ? (int) $request->input('campaign_id') : null,
            scheduledAt: $request->input('scheduled_at'),
            executedAt: $request->input('executed_at'),
            status: $request->input('status'),
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
            'name' => $this->name,
            'type' => $this->type,
            'device_id' => $this->deviceId,
            'group_id' => $this->groupId,
            'campaign_id' => $this->campaignId,
            'scheduled_at' => $this->scheduledAt,
            'executed_at' => $this->executedAt,
            'status' => $this->status,
        ], fn ($value) => $value !== null);
    }
}
