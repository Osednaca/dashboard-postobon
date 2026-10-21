<?php

namespace App\Services\Mobile;

use App\Models\Device;
use App\Models\Establishment;
use App\Models\Wl35DeviceProfile;
use App\Services\Fleet\UnifiedFleetClient;
use App\Services\PrivateCloud\PrivateCloudClient;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

class MobileDeviceList
{
    public function __construct(private readonly PrivateCloudClient $cloud, private readonly UnifiedFleetClient $fleet) {}

    public function paginate(array $filters, string $path): LengthAwarePaginator
    {
        $z2 = $this->snapshot('z2');
        $wl35 = $this->snapshot('wl35');
        $rows = [];
        $z2Live = collect($z2['devices'])->keyBy(fn (array $item): string => $this->mac($item['deviceId'] ?? ''));
        foreach (Device::with('establishmentProfile')->get() as $device) {
            $identifier = $this->mac($device->mac_address);
            $live = $z2Live->pull($identifier);
            $rows[] = $this->row('z2:'.$device->id, 'z2', $device->name, $identifier, $device->establishmentProfile,
                $z2, $live, $device->last_heartbeat_at?->toIso8601String());
        }
        foreach ($z2Live as $identifier => $live) {
            if ($identifier !== '') {
                $rows[] = $this->row('z2:'.$identifier, 'z2', $live['name'] ?? $identifier, $identifier, null, $z2, $live);
            }
        }
        $wl35Live = collect($wl35['devices'])->where('type', 'wl35')->keyBy(fn (array $item): string => (string) ($item['id'] ?? ''));
        foreach (Wl35DeviceProfile::with('establishmentProfile')->get() as $profile) {
            $live = $wl35Live->pull($profile->device_id);
            $rows[] = $this->row('wl35:'.$profile->device_id, 'wl35', $profile->name, $profile->device_id,
                $profile->establishmentProfile, $wl35, $live);
        }
        foreach ($wl35Live as $identifier => $live) {
            if ($identifier !== '') {
                $rows[] = $this->row('wl35:'.$identifier, 'wl35', $live['name'] ?? $identifier, $identifier, null, $wl35, $live);
            }
        }

        $rows = collect($rows)->filter(function (array $row) use ($filters): bool {
            if (isset($filters['establishment_id']) && ($row['establishment']['id'] ?? null) !== (int) $filters['establishment_id']) {
                return false;
            }
            if (isset($filters['status']) && $row['connection_status'] !== $filters['status']) {
                return false;
            }
            $search = mb_strtolower(trim((string) ($filters['search'] ?? '')));

            return $search === '' || str_contains(mb_strtolower($row['name'].' '.$row['identifier'].' '.($row['establishment']['name'] ?? '')), $search);
        })->sortBy(fn (array $row): string => mb_strtolower($row['name']))->values();
        $perPage = (int) ($filters['per_page'] ?? 20);
        $page = (int) ($filters['page'] ?? 1);

        return new LengthAwarePaginator($rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page, [
            'path' => $path,
            'query' => $filters,
        ]);
    }

    private function snapshot(string $type): array
    {
        return Cache::remember('mobile.device-status.'.$type, max(1, (int) config('mobile.status_cache_seconds', 15)), function () use ($type): array {
            $devices = null;
            try {
                if ($type === 'z2') {
                    $response = $this->cloud->get('/api/devices');
                    if (($response['result'] ?? null) === 0 && is_array($response['devices'] ?? null)) {
                        $devices = $response['devices'];
                    }
                } elseif ($this->fleet->isConfigured()) {
                    $response = $this->fleet->getFleet();
                    if (is_array($response['devices'] ?? null)) {
                        $devices = $response['devices'];
                    }
                }
            } catch (Throwable) {
                // Preserve the local inventory but never turn a failed source into offline.
            }

            return [
                'ok' => $devices !== null,
                'devices' => array_values(array_filter($devices ?? [], 'is_array')),
                'observed_at' => now()->toIso8601String(),
            ];
        });
    }

    private function row(string $id, string $type, ?string $name, string $identifier, ?Establishment $establishment, array $source, ?array $live, ?string $fallbackLastSeen = null): array
    {
        $lastSeen = $this->date($live[$type === 'z2' ? 'lastSeenIso' : 'last_seen'] ?? $fallbackLastSeen);
        $status = 'unknown';
        if ($source['ok'] && $live !== null && is_bool($live['online'] ?? null)) {
            if (! $live['online']) {
                $status = 'offline';
            } elseif ($lastSeen && Carbon::parse($lastSeen)->between(now()->subSeconds((int) config('mobile.status_stale_seconds', 180)), now()->addSeconds(30))) {
                $status = 'online';
            }
        }

        return [
            'id' => $id,
            'type' => $type,
            'name' => filled($name) ? $name : $identifier,
            'identifier' => $identifier,
            'establishment' => $establishment?->only(['id', 'name']),
            'connection_status' => $status,
            'last_seen_at' => $lastSeen,
            'status_observed_at' => $source['ok'] ? $source['observed_at'] : null,
        ];
    }

    private function mac(string $identifier): string
    {
        return strtoupper(str_replace([':', '-'], '', $identifier));
    }

    private function date(?string $value): ?string
    {
        try {
            return filled($value) ? Carbon::parse($value)->toIso8601String() : null;
        } catch (Throwable) {
            return null;
        }
    }
}
