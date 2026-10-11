<?php

namespace App\Services;

use App\Models\Campaign;
use App\Repositories\Contracts\CampaignRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CampaignService extends BaseService
{
    /**
     * CampaignService constructor.
     */
    public function __construct(CampaignRepositoryInterface $campaignRepository)
    {
        parent::__construct($campaignRepository);
    }

    public function create(array $data): Model
    {
        return DB::transaction(function () use ($data): Model {
            $campaign = parent::create(Arr::except($data, ['media_ids', 'media_selection_present', 'is_permanent']));
            $this->syncSelectedMedia($campaign, $data);

            return $campaign;
        });
    }

    public function update(int|string $id, array $data): ?Model
    {
        return DB::transaction(function () use ($id, $data): ?Model {
            $campaign = parent::update($id, Arr::except($data, ['media_ids', 'media_selection_present', 'is_permanent']));
            if ($campaign instanceof Campaign) {
                $this->syncSelectedMedia($campaign, $data);
            }

            return $campaign;
        });
    }

    private function syncSelectedMedia(Campaign $campaign, array $data): void
    {
        if (! array_key_exists('media_ids', $data)) {
            return;
        }

        $ordered = [];
        foreach ($data['media_ids'] as $index => $id) {
            $ordered[(int) $id] = ['order' => $index + 1];
        }
        $campaign->media()->sync($ordered);
        $campaign->unsetRelation('media');
    }

    /**
     * Get campaigns by status.
     *
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
     */
    public function attachMedia(int|string $campaignId, int|string $mediaId, ?int $order = null): void
    {
        $this->repository->attachMedia($campaignId, $mediaId, $order);
    }

    /**
     * Detach media from a campaign.
     */
    public function detachMedia(int|string $campaignId, int|string $mediaId): void
    {
        $this->repository->detachMedia($campaignId, $mediaId);
    }

    /**
     * Transition campaign to scheduled status.
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
     * @param  array<int, string>  $cities
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
     * @param  array<int, int>  $groupIds
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
