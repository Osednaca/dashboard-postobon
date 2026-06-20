<?php

namespace App\DTOs;

use Illuminate\Http\Request;

class DeviceDTO
{
    /**
     * @param string|null $name
     * @param string|null $macAddress
     * @param string|null $firmware
     * @param string|null $hardware
     * @param int|null $rpm
     * @param string|null $status
     * @param int|null $locationId
     * @param int|null $groupId
     * @param string|null $lastHeartbeatAt
     * @param float|null $workingHours
     * @param string|null $powerStatus
     */
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $macAddress = null,
        public readonly ?string $firmware = null,
        public readonly ?string $hardware = null,
        public readonly ?int $rpm = null,
        public readonly ?string $status = null,
        public readonly ?int $locationId = null,
        public readonly ?int $groupId = null,
        public readonly ?string $lastHeartbeatAt = null,
        public readonly ?float $workingHours = null,
        public readonly ?string $powerStatus = null,
    ) {
    }

    /**
     * Create a DeviceDTO from a Request.
     *
     * @param Request $request
     * @return self
     */
    public static function fromRequest(Request $request): self
    {
        return new self(
            name: $request->input('name'),
            macAddress: $request->input('mac_address'),
            firmware: $request->input('firmware'),
            hardware: $request->input('hardware'),
            rpm: $request->input('rpm') !== null ? (int) $request->input('rpm') : null,
            status: $request->input('status'),
            locationId: $request->input('location_id') !== null ? (int) $request->input('location_id') : null,
            groupId: $request->input('group_id') !== null ? (int) $request->input('group_id') : null,
            lastHeartbeatAt: $request->input('last_heartbeat_at'),
            workingHours: $request->input('working_hours') !== null ? (float) $request->input('working_hours') : null,
            powerStatus: $request->input('power_status'),
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
            'mac_address' => $this->macAddress,
            'firmware' => $this->firmware,
            'hardware' => $this->hardware,
            'rpm' => $this->rpm,
            'status' => $this->status,
            'location_id' => $this->locationId,
            'group_id' => $this->groupId,
            'last_heartbeat_at' => $this->lastHeartbeatAt,
            'working_hours' => $this->workingHours,
            'power_status' => $this->powerStatus,
        ], fn ($value) => $value !== null);
    }
}
