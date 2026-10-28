<?php

namespace App\Services\Fleet;

use App\Models\Device;
use Closure;
use Illuminate\Support\Facades\DB;

/** Serialize queued work creation against retirement of known Z2 devices. */
class QueueTargetLock
{
    public function create(array $targets, Closure $create): mixed
    {
        return DB::transaction(function () use ($targets, $create) {
            $keys = collect($targets)->filter(fn ($key) => is_string($key) && str_starts_with(strtolower($key), 'z2:'))
                ->map(fn ($key) => $this->normalize(substr($key, 3)))->unique()->values()->all();

            // First resolve IDs without locking the entire inventory on databases
            // that cannot use an index for normalized MAC comparisons.
            $ids = $keys === [] ? [] : Device::withTrashed()
                ->whereRaw("UPPER(REPLACE(REPLACE(mac_address, ':', ''), '-', '')) IN (".implode(',', array_fill(0, count($keys), '?')).')', $keys)
                ->pluck('id')->all();
            $blocked = $ids === [] ? [] : Device::withTrashed()
                ->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()
                ->filter(fn (Device $device) => $device->trashed() || $device->retired_for_onboarding_at !== null)
                ->map(fn (Device $device) => $this->normalize((string) $device->mac_address))->all();

            $allowed = array_values(array_filter($targets, fn ($key) => ! is_string($key)
                || ! str_starts_with(strtolower($key), 'z2:')
                || ! in_array($this->normalize(substr($key, 3)), $blocked, true)));
            if ($allowed === []) {
                throw new \RuntimeException('Los dispositivos seleccionados ya no están disponibles. Actualiza la selección.');
            }

            return $create($allowed);
        }, 3);
    }

    private function normalize(string $mac): string
    {
        return strtoupper(str_replace([':', '-'], '', trim($mac)));
    }
}
