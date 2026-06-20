<?php

namespace App\Services;

use App\Models\Campaign;
use App\Repositories\Contracts\CampaignRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class CampaignService extends BaseService
{
    /**
     * CampaignService constructor.
     *
     * @param CampaignRepositoryInterface $campaignRepository
     */
    public function __construct(CampaignRepositoryInterface $campaignRepository)
    {
        parent::__construct($campaignRepository);
    }

    /**
     * Get campaigns by status.
     *
     * @param string $status
     * @return Collection<int, Campaign>
     */
    public function getByStatus(string $status): Collection
    {
        return $this->repository->getByStatus($status);
    }

    /**
     * Get active campaigns.
     *
     * @return Collection<int, Campaign>
     */
    public function getActive(): Collection
    {
        return $this->repository->getActive();
    }

    /**
     * Attach media to a campaign.
     *
     * @param int|string $campaignId
     * @param int|string $mediaId
     * @param int|null $order
     * @return void
     */
    public function attachMedia(int|string $campaignId, int|string $mediaId, ?int $order = null): void
    {
        $this->repository->attachMedia($campaignId, $mediaId, $order);
    }

    /**
     * Detach media from a campaign.
     *
     * @param int|string $campaignId
     * @param int|string $mediaId
     * @return void
     */
    public function detachMedia(int|string $campaignId, int|string $mediaId): void
    {
        $this->repository->detachMedia($campaignId, $mediaId);
    }

    /**
     * Transition campaign to scheduled status.
     *
     * @param int|string $id
     * @return Campaign|null
     */
    public function schedule(int|string $id): ?Campaign
    {
        $campaign = $this->repository->find($id);

        if ($campaign instanceof Campaign && $campaign->status === 'draft') {
            return $this->repository->update($id, ['status' => 'scheduled']);
        }

        /** @var Campaign|null */
        return $campaign;
    }

    /**
     * Activate a campaign.
     *
     * @param int|string $id
     * @return Campaign|null
     */
    public function activate(int|string $id): ?Campaign
    {
        $campaign = $this->repository->find($id);

        if ($campaign instanceof Campaign && in_array($campaign->status, ['draft', 'scheduled', 'paused'])) {
            return $this->repository->update($id, ['status' => 'active']);
        }

        /** @var Campaign|null */
        return $campaign;
    }

    /**
     * Pause a campaign.
     *
     * @param int|string $id
     * @return Campaign|null
     */
    public function pause(int|string $id): ?Campaign
    {
        $campaign = $this->repository->find($id);

        if ($campaign instanceof Campaign && $campaign->status === 'active') {
            return $this->repository->update($id, ['status' => 'paused']);
        }

        /** @var Campaign|null */
        return $campaign;
    }

    /**
     * Finish a campaign.
     *
     * @param int|string $id
     * @return Campaign|null
     */
    public function finish(int|string $id): ?Campaign
    {
        $campaign = $this->repository->find($id);

        if ($campaign instanceof Campaign && in_array($campaign->status, ['active', 'paused'])) {
            return $this->repository->update($id, ['status' => 'finished']);
        }

        /** @var Campaign|null */
        return $campaign;
    }

    /**
     * Segment a campaign by cities.
     *
     * @param int|string $id
     * @param array<int, string> $cities
     * @return Campaign|null
     */
    public function segmentByCities(int|string $id, array $cities): ?Campaign
    {
        $campaign = $this->repository->find($id);

        if ($campaign instanceof Campaign) {
            $segmentCities = array_unique(array_merge($campaign->segment_cities ?? [], $cities));
            return $this->repository->update($id, ['segment_cities' => $segmentCities]);
        }

        return null;
    }

    /**
     * Segment a campaign by groups.
     *
     * @param int|string $id
     * @param array<int, int> $groupIds
     * @return Campaign|null
     */
    public function segmentByGroups(int|string $id, array $groupIds): ?Campaign
    {
        $campaign = $this->repository->find($id);

        if ($campaign instanceof Campaign) {
            $segmentGroups = array_unique(array_merge($campaign->segment_groups ?? [], $groupIds));
            return $this->repository->update($id, ['segment_groups' => $segmentGroups]);
        }

        return null;
    }
}
