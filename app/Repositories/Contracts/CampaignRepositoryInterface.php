<?php

namespace App\Repositories\Contracts;

use App\Models\Campaign;
use Illuminate\Database\Eloquent\Collection;

interface CampaignRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Get campaigns by status.
     *
     * @param string $status
     * @return Collection<int, Campaign>
     */
    public function getByStatus(string $status): Collection;

    /**
     * Get active campaigns.
     *
     * @return Collection<int, Campaign>
     */
    public function getActive(): Collection;

    /**
     * Attach media to a campaign.
     *
     * @param int|string $campaignId
     * @param int|string $mediaId
     * @param int|null $order
     * @return void
     */
    public function attachMedia(int|string $campaignId, int|string $mediaId, ?int $order = null): void;

    /**
     * Detach media from a campaign.
     *
     * @param int|string $campaignId
     * @param int|string $mediaId
     * @return void
     */
    public function detachMedia(int|string $campaignId, int|string $mediaId): void;
}
