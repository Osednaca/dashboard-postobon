<?php

namespace App\Services\Fleet;

use App\Models\Device;
use App\Models\FleetOperation;
use App\Models\FleetUpload;
use Illuminate\Support\Facades\DB;

class RetiredDeviceJobExclusions
{
    /** Called inside the retirement transaction while the device row is locked. */
    public function snapshot(Device $device): void
    {
        $mac = $this->normalize($device->mac_address);
        if ($mac === '') {
            return;
        }
        // Locking reads see rows committed while retirement waited for the device lock.
        foreach (FleetUpload::whereIn('status', ['queued', 'processing'])->lockForUpdate()->cursor() as $upload) {
            if ($this->contains($upload->targets ?? [], $mac)) {
                $this->record($device, 'fleet_upload', (string) $upload->id);
            }
        }
        foreach (FleetOperation::whereIn('status', ['queued', 'processing'])->lockForUpdate()->cursor() as $operation) {
            if ($this->contains($operation->payload['targets'] ?? [], $mac)) {
                $this->record($device, 'fleet_operation', (string) $operation->id);
            }
        }
    }

    /** @return array{0: array, 1: array} Allowed and excluded target keys. */
    public function filter(string $scope, string $resourceId, array $targets): array
    {
        $excluded = DB::table('devices')
            ->whereNotNull('retired_for_onboarding_at')
            ->orWhereExists(function ($query) use ($scope, $resourceId): void {
                $query->selectRaw('1')->from('device_retirement_exclusions')
                    ->whereColumn('device_retirement_exclusions.device_id', 'devices.id')
                    ->where('scope', $scope)->where('resource_id', $resourceId);
            })->pluck('mac_address')->map(fn ($mac) => $this->normalize($mac))->filter(fn ($mac) => $mac !== '')->all();

        $allowed = [];
        $skipped = [];
        foreach ($targets as $target) {
            if (is_string($target) && str_starts_with(strtolower($target), 'z2:')
                && in_array($this->normalize(substr($target, 3)), $excluded, true)) {
                $skipped[] = $target;
            } else {
                $allowed[] = $target;
            }
        }

        return [$allowed, $skipped];
    }

    private function record(Device $device, string $scope, string $resourceId): void
    {
        DB::table('device_retirement_exclusions')->insertOrIgnore([
            'device_id' => $device->id, 'scope' => $scope, 'resource_id' => $resourceId,
        ]);
    }

    private function contains(array $targets, string $mac): bool
    {
        foreach ($targets as $target) {
            if (is_string($target) && str_starts_with(strtolower($target), 'z2:')
                && $this->normalize(substr($target, 3)) === $mac) {
                return true;
            }
        }

        return false;
    }

    private function normalize(?string $mac): string
    {
        return strtoupper(str_replace([':', '-'], '', trim($mac ?? '')));
    }
}
