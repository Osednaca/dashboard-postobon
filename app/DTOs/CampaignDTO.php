<?php

namespace App\DTOs;

use Illuminate\Http\Request;

class CampaignDTO
{
    /**
     * @param string|null $name
     * @param string|null $description
     * @param string|null $status
     * @param int|null $priority
     * @param string|null $startDate
     * @param string|null $endDate
     * @param array<int, string>|null $segmentCities
     * @param array<int, int>|null $segmentGroups
     * @param int|null $createdBy
     */
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $description = null,
        public readonly ?string $status = null,
        public readonly ?int $priority = null,
        public readonly ?string $startDate = null,
        public readonly ?string $endDate = null,
        public readonly ?array $segmentCities = null,
        public readonly ?array $segmentGroups = null,
        public readonly ?int $createdBy = null,
    ) {
    }

    /**
     * Create a CampaignDTO from a Request.
     *
     * @param Request $request
     * @return self
     */
    public static function fromRequest(Request $request): self
    {
        return new self(
            name: $request->input('name'),
            description: $request->input('description'),
            status: $request->input('status'),
            priority: $request->input('priority') !== null ? (int) $request->input('priority') : null,
            startDate: $request->input('start_date'),
            endDate: $request->input('end_date'),
            segmentCities: $request->input('segment_cities'),
            segmentGroups: $request->input('segment_groups'),
            createdBy: $request->input('created_by') !== null ? (int) $request->input('created_by') : null,
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
            'status' => $this->status,
            'priority' => $this->priority,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'segment_cities' => $this->segmentCities,
            'segment_groups' => $this->segmentGroups,
            'created_by' => $this->createdBy,
        ], fn ($value) => $value !== null);
    }
}
