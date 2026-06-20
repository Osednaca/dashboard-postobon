<?php

namespace App\DTOs;

use Illuminate\Http\Request;

class GroupDTO
{
    /**
     * @param string|null $name
     * @param string|null $description
     */
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $description = null,
    ) {
    }

    /**
     * Create a GroupDTO from a Request.
     *
     * @param Request $request
     * @return self
     */
    public static function fromRequest(Request $request): self
    {
        return new self(
            name: $request->input('name'),
            description: $request->input('description'),
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
            'description' => $this->description,
        ], fn ($value) => $value !== null);
    }
}
