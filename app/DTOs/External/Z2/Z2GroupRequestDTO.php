<?php

namespace App\DTOs\External\Z2;

/**
 * Data Transfer Object for Z2 group request.
 */
class Z2GroupRequestDTO
{
    public function __construct(
        public string $groupId,
        public string $groupName,
        public string $groupParent = '0',
        public string $lang = 'en',
    ) {}

    /**
     * Convert the DTO to an array for API request.
     */
    public function toArray(): array
    {
        return [
            'groupId' => $this->groupId,
            'groupName' => $this->groupName,
            'groupParent' => $this->groupParent,
            'lang' => $this->lang,
        ];
    }
}
