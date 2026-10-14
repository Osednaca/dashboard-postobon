<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Device;
use App\Models\Establishment;
use App\Models\Wl35DeviceProfile;
use App\Services\Fleet\UnifiedFleetClient;
use App\Services\PrivateCloud\PrivateCloudClient;
use Throwable;

/** Read-only web dashboard. The legacy dashboard API retains its existing contract. */
class DashboardSnapshotService
{
    public function __construct(
        private readonly UnifiedFleetClient $fleet,
        private readonly PrivateCloudClient $cloud,
    ) {}

    public function snapshot(): array
    {
        $devices = [];
        foreach (Device::orderBy('id')->get() as $device) {
            $id = $this->identifier('z2', $device->mac_address ?? '');
            $key = 'z2:'.($id ?: 'local-'.$device->id);
            $devices[$key] ??= [
                'key' => $key, 'type' => 'z2', 'name' => $device->name,
                'status' => 'unknown', 'establishment_id' => $device->establishment_id,
                'detail_url' => route('devices.show', $device),
            ];
        }
        foreach (Wl35DeviceProfile::orderBy('id')->get() as $profile) {
            $key = 'wl35:'.$profile->device_id;
            $devices[$key] = [
                'key' => $key, 'type' => 'wl35', 'name' => $profile->name ?: $profile->device_id,
                'status' => 'unknown', 'establishment_id' => $profile->establishment_id,
                'detail_url' => route('devices.wl35.show', $profile->device_id),
            ];
        }

        foreach ($this->telemetry() as $row) {
            if (! is_array($row) || ! in_array($row['type'] ?? null, ['z2', 'wl35'], true)
                || ! is_scalar($row['id'] ?? null)) {
                continue;
            }
            $type = $row['type'];
            $id = $this->identifier($type, (string) $row['id']);
            if ($id === '') {
                continue;
            }
            $key = $type.':'.$id;
            $devices[$key] ??= [
                'key' => $key, 'type' => $type,
                'name' => is_string($row['name'] ?? null) ? $row['name'] : $id,
                'establishment_id' => null,
                'detail_url' => $type === 'wl35' ? route('devices.wl35.show', $id) : null,
            ];
            $online = $row['online'] ?? $row['connected'] ?? null;
            $devices[$key]['status'] = match ($online) {
                true, 1, '1' => 'online',
                false, 0, '0' => 'offline',
                default => 'unknown',
            };
        }

        $devices = collect($devices);
        $establishments = Establishment::orderBy('name')->get();
        $map = [];
        foreach ($establishments as $establishment) {
            if (! $this->validCoordinates($establishment->latitude, $establishment->longitude)) {
                continue;
            }
            $map[] = [
                'id' => $establishment->id, 'name' => $establishment->name,
                'address' => $establishment->address,
                'latitude' => (float) $establishment->latitude,
                'longitude' => (float) $establishment->longitude,
                'devices' => $devices->where('establishment_id', $establishment->id)->values()->all(),
            ];
        }

        return [
            'kpis' => [
                'total_devices' => $devices->count(),
                'online_devices' => $devices->where('status', 'online')->count(),
                'offline_devices' => $devices->where('status', 'offline')->count(),
                'unknown_devices' => $devices->where('status', 'unknown')->count(),
            ],
            'campaign_statuses' => Campaign::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->all(),
            'map_data' => $map,
            'unmapped_establishments' => $establishments->count() - count($map),
        ];
    }

    private function telemetry(): array
    {
        $rows = [];
        if ($this->fleet->isConfigured()) {
            try {
                $snapshot = $this->fleet->getFleet();
                if (($snapshot['success'] ?? true) !== false && is_array($snapshot['devices'] ?? null)) {
                    $rows = array_values(array_filter($snapshot['devices'], function ($device) use ($snapshot): bool {
                        if (! is_array($device)) {
                            return false;
                        }
                        $source = ($device['type'] ?? null) === 'z2' ? 'private_cloud' : 'wl35';

                        return ($snapshot['sources'][$source]['ok'] ?? true) !== false;
                    }));
                }
            } catch (Throwable) {
                // Keep local inventory and try direct Z2 telemetry; never convert a failure into offline.
            }
        }
        if (! collect($rows)->contains(fn ($row) => ($row['type'] ?? null) === 'z2')
            && filled($this->cloud->baseUrl())) {
            try {
                $snapshot = $this->cloud->get('/api/devices');
                if (($snapshot['success'] ?? true) !== false && ($snapshot['result'] ?? 0) === 0
                    && is_array($snapshot['devices'] ?? null)) {
                    foreach ($snapshot['devices'] as $device) {
                        if (is_array($device) && is_scalar($device['deviceId'] ?? null)) {
                            $rows[] = [
                                'type' => 'z2', 'id' => $device['deviceId'], 'name' => $device['name'] ?? null,
                                'online' => $device['online'] ?? null,
                            ];
                        }
                    }
                }
            } catch (Throwable) {
                // Unavailable status is rendered explicitly; no stale database status is promoted to live.
            }
        }

        return $rows;
    }

    private function identifier(string $type, string $id): string
    {
        return $type === 'z2' ? strtoupper(str_replace([':', '-'], '', trim($id))) : trim($id);
    }

    private function validCoordinates(mixed $latitude, mixed $longitude): bool
    {
        return is_numeric($latitude) && is_numeric($longitude)
            && is_finite((float) $latitude) && is_finite((float) $longitude)
            && abs((float) $latitude) <= 90 && abs((float) $longitude) <= 180;
    }
}
