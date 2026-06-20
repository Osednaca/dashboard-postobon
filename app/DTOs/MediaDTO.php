<?php

namespace App\DTOs;

use Illuminate\Http\Request;

class MediaDTO
{
    /**
     * @param string|null $name
     * @param string|null $originalName
     * @param string|null $filePath
     * @param string|null $mimeType
     * @param int|null $size
     * @param int|null $duration
     * @param string|null $thumbnail
     */
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $originalName = null,
        public readonly ?string $filePath = null,
        public readonly ?string $mimeType = null,
        public readonly ?int $size = null,
        public readonly ?int $duration = null,
        public readonly ?string $thumbnail = null,
    ) {
    }

    /**
     * Create a MediaDTO from a Request.
     *
     * @param Request $request
     * @return self
     */
    public static function fromRequest(Request $request): self
    {
        return new self(
            name: $request->input('name'),
            originalName: $request->input('original_name'),
            filePath: $request->input('file_path'),
            mimeType: $request->input('mime_type'),
            size: $request->input('size') !== null ? (int) $request->input('size') : null,
            duration: $request->input('duration') !== null ? (int) $request->input('duration') : null,
            thumbnail: $request->input('thumbnail'),
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
            'original_name' => $this->originalName,
            'file_path' => $this->filePath,
            'mime_type' => $this->mimeType,
            'size' => $this->size,
            'duration' => $this->duration,
            'thumbnail' => $this->thumbnail,
        ], fn ($value) => $value !== null);
    }
}
