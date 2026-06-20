<?php

namespace App\DTOs\External\Z2;

/**
 * Data Transfer Object for Z2 device power request.
 */
class Z2DevicePowerRequestDTO
{
    public function __construct(
        public string $deviceId,
        public int $power,
        public string $mac,
        public string $password,
        public string $lang = 'en',
    ) {}

    /**
     * Convert the DTO to an array for API request.
     */
    public function toArray(): array
    {
        return [
            'deviceId' => $this->deviceId,
            'power' => $this->power,
            'mac' => $this->mac,
            'password' => $this->password,
            'lang' => $this->lang,
        ];
    }
}
