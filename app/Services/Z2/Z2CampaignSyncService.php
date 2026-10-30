<?php

namespace App\Services\Z2;

use App\Models\Campaign;
use App\Models\Device;
use App\Services\Campaigns\CampaignLifecycle;
use App\Services\CampaignTargetCatalog;

/** Compatibility facade: all entry points share the durable campaign lifecycle. */
class Z2CampaignSyncService
{
    public function __construct(private CampaignLifecycle $lifecycle) {}

    public function publishToDevices(Campaign $campaign, array $deviceIds): bool
    {
        if ($campaign->target_devices === null && $deviceIds !== []) {
            $campaign->update(['target_devices' => Device::whereKey($deviceIds)->get()->map(fn ($d) => CampaignTargetCatalog::z2Key($d->mac_address))->unique()->values()->all()]);
        }
        $this->lifecycle->assertQueueConfiguration();
        if ($campaign->status === 'active' && ! $campaign->media()->where(fn ($q) => $q->whereNull('duration')->orWhere('duration', '<=', 0))->exists()) {
            $this->lifecycle->snapshot($campaign);

            return true;
        }

        return $this->lifecycle->activate($campaign);
    }

    public function publishToGroups(Campaign $campaign, array $groupIds): bool
    {
        return $this->publishToDevices($campaign, Device::whereIn('group_id', $groupIds)->pluck('id')->all());
    }

    public function validateRecipients(Campaign $campaign): void
    {
        if ($campaign->target_devices !== null) {
            app(CampaignTargetCatalog::class)->validatedKeys($campaign->target_devices);
        }
    }

    public function activate(Campaign $campaign): bool
    {
        return $this->lifecycle->activate($campaign);
    }

    public function pause(Campaign $campaign): bool
    {
        $campaign->update(['status' => 'paused', 'duration_preparation_token' => null, 'duration_preparation_state' => null]);

        return true;
    }

    public function finish(Campaign $campaign): bool
    {
        $this->lifecycle->finish($campaign);

        return true;
    }
}
