<?php

namespace App\Services\Z2;

use App\Models\Device;
use App\Models\Group;
use Illuminate\Support\Facades\Log;

/**
 * Adaptador de grupos sobre la nube privada (fan-private-cloud).
 *
 * La nube privada no tiene concepto de grupo: los grupos son locales. Toda la
 * gestión es contra la base local y las operaciones por grupo delegan en
 * Z2DeviceService (power on/off, cambio de contenido).
 */
class Z2GroupService
{
    private Z2DeviceService $deviceService;

    public function __construct(Z2DeviceService $deviceService)
    {
        $this->deviceService = $deviceService;
    }

    /**
     * Los grupos son locales en la nube privada; se devuelven tal cual.
     *
     * @return array<int, Group>
     */
    public function syncGroups(): array
    {
        $groups = Group::all();

        Log::info('[PrivateCloud] Groups are local-only; returned '.count($groups).' groups');

        return $groups->all();
    }

    /**
     * Crear un grupo local.
     */
    public function createGroup(string $name): ?Group
    {
        if (Group::where('name', $name)->exists()) {
            Log::warning('[PrivateCloud] Group already exists', ['name' => $name]);

            return null;
        }

        $group = Group::create([
            'name' => $name,
            'description' => 'Grupo creado en el dashboard',
            'z2_group_id' => null,
        ]);

        Log::info('[PrivateCloud] Group created locally', ['id' => $group->id, 'name' => $name]);

        return $group;
    }

    /**
     * Eliminar un grupo local (los dispositivos quedan sin grupo).
     */
    public function deleteGroup(int $groupId): bool
    {
        $group = Group::find($groupId);
        if (! $group) {
            return false;
        }

        Device::where('group_id', $groupId)->update(['group_id' => null]);
        $group->delete();

        Log::info('[PrivateCloud] Group deleted locally', ['id' => $groupId]);

        return true;
    }

    /**
     * Asignar dispositivo a un grupo (local).
     */
    public function assignDevice(int $groupId, string $mac): bool
    {
        $group = Group::find($groupId);
        if (! $group) {
            return false;
        }

        Device::where('mac_address', $mac)->update(['group_id' => $groupId]);

        return true;
    }

    /**
     * Quitar dispositivo del grupo (local).
     */
    public function removeDevice(int $groupId, string $mac): bool
    {
        Device::where('mac_address', $mac)->where('group_id', $groupId)->update(['group_id' => null]);

        return true;
    }

    /**
     * Encender todos los dispositivos del grupo.
     */
    public function powerOnGroup(int $groupId): bool
    {
        return $this->powerGroup($groupId, true);
    }

    /**
     * Apagar todos los dispositivos del grupo.
     */
    public function powerOffGroup(int $groupId): bool
    {
        return $this->powerGroup($groupId, false);
    }

    private function powerGroup(int $groupId, bool $powerOn): bool
    {
        $devices = Device::where('group_id', $groupId)->get();
        $success = true;

        foreach ($devices as $device) {
            $result = $powerOn
                ? $this->deviceService->powerOn($device->mac_address)
                : $this->deviceService->powerOff($device->mac_address);

            if (! $result) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Cambiar contenido de todos los dispositivos del grupo.
     */
    public function changeGroupContent(int $groupId, string $uiCode): bool
    {
        $devices = Device::where('group_id', $groupId)->get();
        $success = true;

        foreach ($devices as $device) {
            $result = $this->deviceService->changeVideo($device->mac_address, $uiCode);

            if (! $result) {
                $success = false;
            }
        }

        return $success;
    }
}