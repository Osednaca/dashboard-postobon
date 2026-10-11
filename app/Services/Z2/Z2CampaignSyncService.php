<?php

namespace App\Services\Z2;

use App\Jobs\PlayFleetMediaJob;
use App\Models\Campaign;
use App\Models\Device;
use App\Models\FleetUpload;
use App\Models\Group;
use App\Services\CampaignTargetCatalog;
use App\Services\Fleet\MediaPreviewSource;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
        if ($campaign->target_devices !== null) {
            return $this->queueExplicitRecipients($campaign);
        }
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

    public function validateRecipients(Campaign $campaign): void
    {
        if ($campaign->target_devices !== null) {
            app(CampaignTargetCatalog::class)->validatedKeys($campaign->target_devices);
        }
    }

    private function queueExplicitRecipients(Campaign $campaign): bool
    {
        $targets = app(CampaignTargetCatalog::class)->validatedKeys($campaign->target_devices);
        if ($targets === []) {
            return true;
        }
        $media = $campaign->media()->first();
        if ($media === null) {
            return true;
        }
        if (! str_starts_with($media->mime_type, 'video/') || app(MediaPreviewSource::class)->resolve($media) === null) {
            throw new \RuntimeException('La campaña necesita un video disponible antes de publicarse.');
        }
        $connection = config('queue.default');
        $driver = config('queue.connections.'.$connection.'.driver');
        if (! in_array($driver, ['database', 'redis'], true)
            || (int) config('queue.connections.'.$connection.'.retry_after') <= 1900) {
            throw new \RuntimeException('La publicación de campañas requiere una cola asíncrona con retry_after mayor a 1900 segundos.');
        }
        $id = (string) Str::uuid();
        $name = basename((string) ($media->original_name ?: $media->name));
        if (! str_ends_with(mb_strtolower($name), '.mp4')) {
            $name .= '.mp4';
        }
        $upload = FleetUpload::create([
            'id' => $id, 'user_id' => auth()->id() ?? $campaign->created_by,
            'source_media_id' => $media->id, 'original_name' => Str::limit($name, 250, ''),
            'file_path' => 'fleet-uploads/'.$id.'.mp4', 'targets' => $targets,
            'play_after_upload' => true, 'status' => 'queued', 'phase' => 'queued', 'progress' => 10,
        ]);
        try {
            PlayFleetMediaJob::dispatch($id);
        } catch (\Throwable $exception) {
            $upload->delete();
            throw $exception;
        }

        return true;
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
