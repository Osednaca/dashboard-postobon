<?php

namespace App\DTOs;

use Illuminate\Http\Request;

class CampaignStatisticDTO
{
    /**
     * @param int|null $campaignId
     * @param int|null $deviceId
     * @param int|null $impressions
     * @param int|null $plays
     * @param float|null $duration
     * @param string|null $date
     */
    public function __construct(
        public readonly ?int $campaignId = null,
        public readonly ?int $deviceId = null,
        public readonly ?int $impressions = null,
        public readonly ?int $plays = null,
        public readonly ?float $duration = null,
        public readonly ?string $date = null,
    ) {
    }

    /**
     * Create a CampaignStatisticDTO from a Request.
     *
     * @param Request $request
     * @return self
     */
    public static function fromRequest(Request $request): self
    {
        return new self(
            campaignId: $request->input('campaign_id') !== null ? (int) $request->input('campaign_id') : null,
            deviceId: $request->input('device_id') !== null ? (int) $request->input('device_id') : null,
            impressions: $request->input('impressions') !== null ? (int) $request->input('impressions') : null,
            plays: $request->input('plays') !== null ? (int) $request->input('plays') : null,
            duration: $request->input('duration') !== null ? (float) $request->input('duration') : null,
            date: $request->input('date'),
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
            'campaign_id' => $this->campaignId,
            'device_id' => $this->deviceId,
            'impressions' => $this->impressions,
            'plays' => $this->plays,
            'duration' => $this->duration,
            'date' => $this->date,
        ], fn ($value) => $value !== null);
    }
}
