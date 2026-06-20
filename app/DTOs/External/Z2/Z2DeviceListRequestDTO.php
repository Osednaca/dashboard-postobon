<?php

namespace App\DTOs\External\Z2;

/**
 * Data Transfer Object for Z2 device list request.
 */
class Z2DeviceListRequestDTO
{
    public function __construct(
        public string $groupId,
        public string $search = '',
        public int $page = 0,
        public int $rows = 50,
    ) {}

    /**
     * Convert the DTO to an array for API request.
     */
    public function toArray(): array
    {
        return [
            'groupId' => $this->groupId,
            'search' => $this->search,
            'page' => $this->page,
            'rows' => $this->rows,
        ];
    }
}
