<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Group;
use App\Models\Wl35DeviceProfile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GroupCreationService
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    /** Local catalog only: grouping never registers devices or contacts a hardware gateway. */
    public function devices(): array
    {
        $items = [];
        foreach (['z2' => Device::class, 'wl35' => Wl35DeviceProfile::class] as $type => $model) {
            foreach ($model::with(['group', 'establishmentProfile.businessType'])->orderBy('name')->get() as $device) {
                $id = $type === 'z2' ? (string) $device->id : $device->device_id;
                $establishment = $device->establishmentProfile;
                $items[] = [
                    'key' => $type.':'.$id, 'id' => $id, 'type' => $type, 'name' => $device->name,
                    'group_id' => $device->group?->id, 'group_name' => $device->group?->name,
                    'establishment_name' => $establishment?->name,
                    'business_type_id' => $establishment?->business_type_id,
                    'business_type_name' => $establishment?->businessType?->name,
                ];
            }
        }

        return $items;
    }

    public function create(array $data, int $userId): Group
    {
        return DB::transaction(function () use ($data, $userId): Group {
            $members = [];
            foreach ($data['device_keys'] ?? [] as $key) {
                [$type, $id] = explode(':', $key, 2);
                $device = $type === 'z2'
                    ? Device::whereKey($id)->lockForUpdate()->first()
                    : Wl35DeviceProfile::where('device_id', $id)->lockForUpdate()->first();
                if ($device === null) {
                    throw ValidationException::withMessages(['device_keys' => 'Un dispositivo ya no está disponible. Revisa la selección.']);
                }
                $members[] = [$key, $device];
            }

            $group = Group::create(Arr::except($data, ['device_keys']));
            $changes = [];
            foreach ($members as [$key, $device]) {
                $changes[] = ['device_key' => $key, 'previous_group_id' => $device->group_id];
                $device->group_id = $group->id;
                $device->save();
            }
            $this->auditLog->log('create', 'Group', $group->id, $userId, [
                'name' => $group->name, 'business_type_id' => $group->business_type_id,
                'devices' => $changes,
            ]);

            return $group;
        });
    }

    /** Add selected members and remove only explicitly selected current members. */
    public function update(Group $group, array $data, int $userId): Group
    {
        return DB::transaction(function () use ($group, $data, $userId): Group {
            $group = Group::whereKey($group->id)->lockForUpdate()->firstOrFail();
            $changes = [];
            foreach (array_unique($data['remove_device_keys'] ?? []) as $key) {
                [$type, $id] = array_pad(explode(':', $key, 2), 2, null);
                $device = match ($type) {
                    'z2' => Device::whereKey($id)->lockForUpdate()->first(),
                    'wl35' => Wl35DeviceProfile::where('device_id', $id)->lockForUpdate()->first(),
                    default => null,
                };
                // A stale form must not unlink a device moved to another group meanwhile.
                if ($device === null || $device->group_id !== $group->id) {
                    continue;
                }

                $changes[] = ['device_key' => $key, 'previous_group_id' => $group->id, 'action' => 'removed'];
                $device->group_id = null;
                $device->save();
            }
            foreach (array_unique($data['device_keys'] ?? []) as $key) {
                [$type, $id] = array_pad(explode(':', $key, 2), 2, null);
                $device = match ($type) {
                    'z2' => Device::whereKey($id)->lockForUpdate()->first(),
                    'wl35' => Wl35DeviceProfile::where('device_id', $id)->lockForUpdate()->first(),
                    default => null,
                };
                if ($device === null) {
                    throw ValidationException::withMessages(['device_keys' => 'Un dispositivo ya no está disponible. Revisa la selección.']);
                }
                if ($device->group_id === $group->id) {
                    continue;
                }

                $changes[] = ['device_key' => $key, 'previous_group_id' => $device->group_id];
                $device->group_id = $group->id;
                $device->save();
            }

            $before = $group->only(['name', 'description']);
            $group->fill(Arr::only($data, ['name', 'description']))->save();
            $this->auditLog->log('update', 'Group', $group->id, $userId, [
                'before' => $before,
                'after' => $group->only(['name', 'description']),
                'devices' => $changes,
            ]);

            return $group;
        });
    }
}
