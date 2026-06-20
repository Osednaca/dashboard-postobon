<?php

namespace App\DTOs\External\Z2;

/**
 * Data Transfer Object for Z2 upload media request.
 */
class Z2UploadMediaRequestDTO
{
    public function __construct(
        public string $deviceId,
        public string $mac,
        public string $fileName,
        public string $fileData,
        public string $fileType,
        public string $lang = 'en',
    ) {}

    /**
     * Convert the DTO to an array for API request.
     */
    public function toArray(): array
    {
        return [
            'deviceId' => $this->deviceId,
            'mac' => $this->mac,
            'fileName' => $this->fileName,
            'fileData' => $this->fileData,
            'fileType' => $this->fileType,
            'lang' => $this->lang,
        ];
    }
}
