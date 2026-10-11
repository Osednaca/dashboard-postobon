<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Device;
use App\Models\Wl35DeviceProfile;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CampaignTargetCatalog
{
    public static function z2Key(string $mac): string
    {
        return 'z2:'.strtoupper(str_replace([':', '-'], '', trim($mac)));
    }

    public function all(): Collection
    {
        $items = collect();
        foreach (['z2' => Device::class, 'wl35' => Wl35DeviceProfile::class] as $type => $model) {
            foreach ($model::with(['location', 'group', 'establishmentProfile'])->orderBy('id')->get() as $device) {
                $id = $type === 'z2' ? $device->mac_address : $device->device_id;
                if (blank($id)) {
                    continue;
                }
                $key = $type === 'z2' ? self::z2Key($id) : 'wl35:'.$id;
                if (! preg_match('/^(z2|wl35):[A-Za-z0-9_.:-]+$/', $key)) {
                    continue;
                }
                // Two local rows may refer to the same physical MAC; select the identity once.
                if ($items->has($key)) {
                    continue;
                }
                $items->put($key, [
                    'key' => $key, 'type' => $type, 'local_id' => $device->id,
                    'name' => $device->name, 'city' => filled($device->city) ? $device->city : ($device->location?->city ?? ''),
                    'group_id' => $device->group?->id, 'group_name' => $device->group?->name,
                    'establishment' => $device->establishmentProfile?->name ?? $device->establishment,
                    'detail_url' => $type === 'z2' ? route('devices.show', $device) : route('devices.wl35.show', $id),
                ]);
            }
        }

        return $items;
    }

    public function validatedKeys(array $keys): array
    {
        $catalog = $this->all();
        if (collect($keys)->contains(fn ($key) => ! is_string($key) || ! $catalog->has($key))
            || count($keys) !== count(array_unique($keys))) {
            throw ValidationException::withMessages(['target_devices' => 'Uno de los dispositivos seleccionados ya no está disponible. Revisa la selección.']);
        }

        return array_values($keys);
    }

    public function forCampaign(Campaign $campaign): Collection
    {
        $catalog = $this->all();
        $keys = $campaign->target_devices;
        if ($keys === null) {
            $ids = $campaign->deviceCampaigns()->pluck('device_id');
            $keys = Device::whereIn('id', $ids)->get()->filter(fn (Device $device) => filled($device->mac_address))
                ->map(fn (Device $device) => self::z2Key($device->mac_address))->unique()->values()->all();
        }

        return collect($keys)->map(fn ($key) => $catalog->get($key, [
            'key' => $key, 'name' => 'Dispositivo no disponible', 'type' => str_starts_with($key, 'wl35:') ? 'wl35' : 'z2',
            'city' => '', 'group_name' => null, 'establishment' => null, 'detail_url' => null,
        ]))->values();
    }
}
