<?php

namespace App\DTOs;

use Illuminate\Http\Request;

class SubscriptionDTO
{
    /**
     * @param string|null $name
     * @param string|null $status
     * @param string|null $startDate
     * @param string|null $endDate
     * @param int|null $alertDaysBefore
     * @param array<string, mixed>|null $restrictions
     */
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $status = null,
        public readonly ?string $startDate = null,
        public readonly ?string $endDate = null,
        public readonly ?int $alertDaysBefore = null,
        public readonly ?array $restrictions = null,
    ) {
    }

    /**
     * Create a SubscriptionDTO from a Request.
     *
     * @param Request $request
     * @return self
     */
    public static function fromRequest(Request $request): self
    {
        return new self(
            name: $request->input('name'),
            status: $request->input('status'),
            startDate: $request->input('start_date'),
            endDate: $request->input('end_date'),
            alertDaysBefore: $request->input('alert_days_before') !== null ? (int) $request->input('alert_days_before') : null,
            restrictions: $request->input('restrictions'),
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
            'status' => $this->status,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'alert_days_before' => $this->alertDaysBefore,
            'restrictions' => $this->restrictions,
        ], fn ($value) => $value !== null);
    }
}
