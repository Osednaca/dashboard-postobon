<?php

namespace App\Services\Z2;

use App\Services\PrivateCloud\PrivateCloudClient;
use Illuminate\Support\Facades\Cache;

class DeviceContentRemovalTracker
{
    public function __construct(private readonly PrivateCloudClient $client) {}

    public function accepted(string $mac, ?string $filename, array $files = []): void
    {
        $entries = $filename === null ? [] : Cache::get($this->key($mac), []);
        $entries[$filename ?? '*'] = [
            'filename' => $filename, 'files' => $filename === null ? $files : [$filename],
            'expires_at' => now()->addMinutes(10)->getTimestamp(),
            'retain_until' => now()->addDay()->getTimestamp(),
        ];
        Cache::put($this->key($mac), $entries, now()->addDay());
    }

    public function reportedFiles(string $mac): array
    {
        return $this->playlist($this->snapshot($mac)) ?? [];
    }

    /** @return array{playlist: array, removals: array, available: bool} */
    public function state(string $mac): array
    {
        $device = $this->snapshot($mac);
        $playlist = $this->playlist($device);
        $entries = Cache::get($this->key($mac), []);
        $removals = [];
        $hidden = [];
        foreach ($entries as $key => &$entry) {
            if (now()->getTimestamp() >= $entry['retain_until']) {
                unset($entries[$key]);

                continue;
            }
            $absent = $playlist !== null && ($entry['filename'] === null
                ? ($entry['files'] ? array_intersect($playlist, $entry['files']) === [] : $playlist === [])
                : ! in_array($entry['filename'], $playlist, true));
            $expired = now()->getTimestamp() >= $entry['expires_at'];
            if ($absent && $expired) {
                // Do not drop suppression early: an intermediate optimistic list can be overwritten.
                // The private API exposes a persisted playlist without per-field freshness.
                unset($entries[$key]);

                continue;
            }
            $status = $expired ? 'expired' : 'pending';
            $removals[] = ['filename' => $entry['filename'], 'status' => $status];
            if ($status === 'pending') {
                $hidden = array_merge($hidden, $entry['files']);
            }
        }
        unset($entry);
        if ($entries) {
            // Reads do not extend the original wait, only retain an expiry notice temporarily.
            Cache::put($this->key($mac), $entries, max(array_column($entries, 'retain_until')) - now()->getTimestamp());
        } else {
            Cache::forget($this->key($mac));
        }

        return [
            'playlist' => array_values(array_diff($playlist ?? [], $hidden)),
            'removals' => $removals, 'available' => $playlist !== null,
        ];
    }

    private function snapshot(string $mac): ?array
    {
        $response = $this->client->get('/api/devices/'.strtoupper(str_replace(':', '', trim($mac))));

        return ($response['result'] ?? null) === 0 && is_array($response['device'] ?? null)
            ? $response['device'] : null;
    }

    private function playlist(?array $device): ?array
    {
        $playlist = $device['playlist'] ?? null;
        if (! is_array($playlist) || ! array_is_list($playlist)) {
            return null;
        }
        foreach ($playlist as $filename) {
            if (! is_string($filename) || $filename === '') {
                return null;
            }
        }

        return array_values(array_unique($playlist));
    }

    private function key(string $mac): string
    {
        return 'device-content-removals:'.strtoupper(str_replace(':', '', trim($mac)));
    }
}
