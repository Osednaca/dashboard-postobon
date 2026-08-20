<?php

namespace App\Services\Z2;

use App\Models\Campaign;
use App\Models\Device;
use App\Models\Group;
use Illuminate\Support\Facades\Log;

class Z2CampaignSyncService
{
    private Z2PlaylistService $playlistService;

    public function __construct(Z2PlaylistService $playlistService)
    {
        $this->playlistService = $playlistService;
    }

    /**
     * Publish campaign to specific devices.
     */
    public function publishToDevices(Campaign $campaign, array $deviceIds): bool
    {
        $success = true;

        foreach ($deviceIds as $deviceId) {
            $device = Device::find($deviceId);
            if (! $device) {
                continue;
            }

            // Get campaign media
            $media = $campaign->media()->first();
            if (! $media) {
                Log::warning('[PrivateCloud] Campaign has no media', ['campaign' => $campaign->id]);
                continue;
            }

            $result = $this->playlistService->assignVideoToDevice(
                $device->mac_address,
                $media->file_path
            );

            if (! $result) {
                $success = false;
            }
        }

        if ($success) {
            $campaign->update(['status' => 'active']);
        }

        return $success;
    }

    /**
     * Publish campaign to groups.
     */
    public function publishToGroups(Campaign $campaign, array $groupIds): bool
    {
        $success = true;

        foreach ($groupIds as $groupId) {
            $group = Group::find($groupId);
            if (! $group) {
                continue;
            }

            $media = $campaign->media()->first();
            if (! $media) {
                continue;
            }

            $result = $this->playlistService->assignVideoToGroup(
                $group->id,
                $media->file_path
            );

            if (! $result) {
                $success = false;
            }
        }

        if ($success) {
            $campaign->update(['status' => 'active']);
        }

        return $success;
    }

    /**
     * Activate campaign in cloud.
     */
    public function activate(Campaign $campaign): bool
    {
        $campaign->update(['status' => 'active']);
        return true;
    }

    /**
     * Pause campaign in cloud.
     */
    public function pause(Campaign $campaign): bool
    {
        $campaign->update(['status' => 'paused']);
        return true;
    }

    /**
     * Finish campaign in cloud.
     */
    public function finish(Campaign $campaign): bool
    {
        $campaign->update(['status' => 'finished']);
        return true;
    }
}