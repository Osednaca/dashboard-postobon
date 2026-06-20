<?php

namespace App\Repositories;

use App\Models\Campaign;
use App\Repositories\Contracts\CampaignRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class CampaignRepository extends BaseRepository implements CampaignRepositoryInterface
{
    /**
     * CampaignRepository constructor.
     *
     * @param Campaign $campaign
     */
    public function __construct(Campaign $campaign)
    {
        parent::__construct($campaign);
    }

    /**
     * @inheritDoc
     */
    public function getByStatus(string $status): Collection
    {
        return $this->model->where('status', $status)->get();
    }

    /**
     * @inheritDoc
     */
    public function getActive(): Collection
    {
        return $this->model->where('status', 'active')->get();
    }

    /**
     * @inheritDoc
     */
    public function attachMedia(int|string $campaignId, int|string $mediaId, ?int $order = null): void
    {
        $campaign = $this->find($campaignId);

        if ($campaign) {
            /** @var Campaign $campaign */
            $campaign->media()->attach($mediaId, ['order' => $order]);
        }
    }

    /**
     * @inheritDoc
     */
    public function detachMedia(int|string $campaignId, int|string $mediaId): void
    {
        $campaign = $this->find($campaignId);

        if ($campaign) {
            /** @var Campaign $campaign */
            $campaign->media()->detach($mediaId);
        }
    }
}
