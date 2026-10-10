<?php

namespace App\Services\Fleet;

use App\Models\Device;
use App\Models\Media;
use App\Models\Wl35DeviceMedia;
use App\Models\Wl35DeviceProfile;
use App\Services\PrivateCloud\PrivateCloudClient;
use Throwable;

class DevicePreviewService
{
    public function __construct(
        private readonly UnifiedFleetClient $fleet,
        private readonly PrivateCloudClient $cloud,
        private readonly MediaPreviewSource $sources,
    ) {}

    /** @return array<int, array<string, mixed>> */
    public function snapshot(): array
    {
        $live = [];
        if ($this->fleet->isConfigured()) {
            try {
                $live = $this->fleet->getFleet()['devices'] ?? [];
            } catch (Throwable) {
                // A failed gateway must not hide the directly available Z2 telemetry.
            }
        }
        if (! collect($live)->contains(fn ($device) => ($device['type'] ?? null) === 'z2')) {
            try {
                $cloudDevices = $this->cloud->get('/api/devices')['devices'] ?? [];
            } catch (Throwable) {
                $cloudDevices = [];
            }
            foreach ($cloudDevices as $device) {
                if (empty($device['deviceId'])) {
                    continue;
                }
                $live[] = [
                    'type' => 'z2', 'id' => $device['deviceId'], 'name' => $device['name'] ?? $device['deviceId'],
                    'online' => $device['online'] ?? false,
                    'power' => isset($device['power']) ? (int) $device['power'] === 1 : null,
                    'current_video' => $device['displayImageId'] ?? null,
                    'last_seen' => $device['lastSeenIso'] ?? null,
                ];
            }
        }

        $devices = collect($live)->filter(fn ($device) => in_array($device['type'] ?? null, ['z2', 'wl35'], true)
            && filled($device['id'] ?? null))
            ->keyBy(fn ($device) => $device['type'].':'.$device['id']);
        foreach (Device::all() as $device) {
            $id = strtoupper(str_replace(':', '', $device->mac_address ?? ''));
            if ($id !== '') {
                $key = 'z2:'.$id;
                $devices[$key] = array_merge($devices[$key] ?? ['type' => 'z2', 'id' => $id], [
                    'name' => $device->name, 'detail_url' => route('devices.show', $device),
                ]);
            }
        }
        foreach (Wl35DeviceProfile::all() as $profile) {
            $key = 'wl35:'.$profile->device_id;
            $devices[$key] = array_merge($devices[$key] ?? ['type' => 'wl35', 'id' => $profile->device_id], [
                'name' => $profile->name ?: $profile->device_id,
            ]);
        }

        $filenames = $devices->where('type', 'z2')->pluck('current_video')->filter()->map(fn ($name) => (string) $name);
        $media = Media::whereIn('file_path', $filenames)->orWhereIn('original_name', $filenames)->get();
        $mappings = Wl35DeviceMedia::with('media')->whereIn('device_id', $devices->where('type', 'wl35')->pluck('id'))
            ->get()->keyBy(fn ($mapping) => $mapping->device_id.':'.$mapping->video_index);

        return $devices->map(function (array $device) use ($media, $mappings): array {
            $current = $device['current_video'] ?? null;
            $source = null;
            $status = 'unavailable';
            if (array_key_exists('online', $device)) {
                $status = ! $device['online'] ? 'offline' : (($device['power'] ?? null) === false ? 'powered_off' : 'unknown');
                if ($device['online'] && ($device['power'] ?? null) === true
                    && ($device['type'] !== 'wl35' || (($device['connected'] ?? false) && ($device['session_ready'] ?? false)))
                    && ! ($device['uploading'] ?? false)) {
                    $status = 'no_content';
                    if ($current !== null && $current !== '' && $current !== 'null') {
                        if ($device['type'] === 'z2') {
                            $source = $media->firstWhere('file_path', (string) $current);
                            if ($source === null) {
                                $matchingNames = $media->where('original_name', (string) $current);
                                $source = $matchingNames->count() === 1 ? $matchingNames->first() : null;
                            }
                        } elseif (filter_var($current, FILTER_VALIDATE_INT) !== false
                            && (int) $current > 0 && (int) $current <= (int) ($device['video_count'] ?? 0)) {
                            $source = $mappings->get($device['id'].':'.(int) $current)?->media;
                        }
                        $status = $source === null ? 'missing_mapping' : (str_starts_with($source->mime_type, 'video/')
                            && $this->sources->resolve($source) ? 'ready' : 'missing_file');
                    }
                }
            }

            return [
                'key' => $device['type'].':'.$device['id'], 'type' => $device['type'], 'id' => (string) $device['id'],
                'name' => $device['name'] ?? $device['id'], 'status' => $status,
                'current_video' => $current, 'media_name' => $source?->name,
                'url' => $status === 'ready' ? route('media.content', $source) : null,
                'last_seen' => $device['last_seen'] ?? null,
                'detail_url' => $device['detail_url'] ?? ($device['type'] === 'wl35'
                    ? route('devices.wl35.show', $device['id']) : null),
            ];
        })->values()->all();
    }
}
