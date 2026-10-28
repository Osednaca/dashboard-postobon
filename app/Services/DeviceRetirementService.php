<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Device;
use App\Models\DeviceOnboarding;
use App\Models\Schedule;
use App\Models\User;
use App\Services\Fleet\RetiredDeviceJobExclusions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeviceRetirementService
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function retire(Device $device, User $actor): void
    {
        DB::transaction(function () use ($device, $actor): void {
            $device = Device::whereKey($device->id)->lockForUpdate()->firstOrFail();
            $mac = strtoupper(str_replace([':', '-'], '', trim((string) $device->mac_address)));
            if ($mac !== '' && DeviceOnboarding::where('active_identifier', $mac)->where('expires_at', '>', now())->exists()) {
                throw ValidationException::withMessages(['device' => 'Hay una instalación en curso para este ventilador. Finalízala o cancélala antes de eliminarlo.']);
            }
            // Preserve old rules as history, but do not apply them to this installation again.
            foreach (['schedule' => Schedule::query(), 'campaign' => Campaign::withTrashed()] as $scope => $query) {
                $query->select('id')->orderBy('id')->chunkById(500, function ($items) use ($scope, $device): void {
                    DB::table('device_retirement_exclusions')->insertOrIgnore($items->map(fn ($item) => [
                        'device_id' => $device->id, 'scope' => $scope, 'resource_id' => (string) $item->id,
                    ])->all());
                });
            }
            app(RetiredDeviceJobExclusions::class)->snapshot($device);
            $previousEstablishment = $device->establishment_id;
            $previousGroup = $device->group_id;
            $device->forceFill(['establishment_id' => null, 'group_id' => null, 'retired_for_onboarding_at' => now()])->save();
            $device->delete();
            $this->audit->log('device_retired_for_reinstallation', 'Device', $device->id, $actor->id, [
                'mac_address' => $mac, 'previous_establishment_id' => $previousEstablishment, 'previous_group_id' => $previousGroup,
            ]);
        }, 3);
    }
}
