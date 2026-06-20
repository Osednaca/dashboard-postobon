<?php

namespace App\Services\Z2;

use App\Models\Device;
use App\Models\Group;
use Illuminate\Support\Facades\Log;

class Z2GroupService
{
    private FanCloudService $client;

    public function __construct(FanCloudService $client)
    {
        $this->client = $client;
    }

    /**
     * Sync groups from cloud.
     */
    public function syncGroups(): array
    {
        $response = $this->client->request('POST', '/User/groupList', [
            'userName' => $this->client->username,
        ]);

        if (! $response || ! isset($response['aaData'])) {
            Log::error('[Z2] Failed to fetch groups from cloud');
            return [];
        }

        $groupsToUpsert = [];
        $groupNames = [];

        foreach ($response['aaData'] as $groupData) {
            $groupName = $groupData['groupName'] ?? null;
            $z2GroupId = $groupData['idGroup'] ?? null;
            if (! $groupName) {
                continue;
            }

            $groupNames[] = $groupName;
            $groupsToUpsert[] = [
                'name' => $groupName,
                'description' => 'Grupo sincronizado desde Z2 Cloud',
                'z2_group_id' => $z2GroupId,
            ];
        }

        // Restore soft-deleted groups that are back in the cloud
        if (! empty($groupNames)) {
            $trashedGroups = Group::onlyTrashed()->whereIn('name', $groupNames)->get();
            foreach ($trashedGroups as $trashedGroup) {
                $trashedGroup->restore();
                Log::info('[Z2] Restored soft-deleted group', ['name' => $trashedGroup->name]);
            }
        }

        // Atomic upsert to avoid race conditions
        if (! empty($groupsToUpsert)) {
            Group::upsert($groupsToUpsert, ['name'], ['description', 'z2_group_id']);
        }

        // Remove groups no longer in cloud
        Group::whereNotIn('name', $groupNames)->delete();

        $syncedGroups = Group::whereIn('name', $groupNames)->get()->all();

        Log::info('[Z2] Synced ' . count($syncedGroups) . ' groups from cloud');

        return $syncedGroups;
    }

    /**
     * Create a new group in cloud.
     */
    public function createGroup(string $name): ?Group
    {
        $response = $this->client->request('POST', '/User/addGroup', [
            'userName' => $this->client->username,
            'groupName' => $name,
        ]);

        if ($response && ($response['result'] ?? -1) === 0) {
            $group = Group::create([
                'name' => $name,
                'description' => 'Grupo creado en Z2 Cloud',
                'z2_group_id' => $response['idGroup'] ?? null,
            ]);
            return $group;
        }

        Log::error('[Z2] Create group failed', ['name' => $name, 'response' => $response]);
        return null;
    }

    /**
     * Delete group from cloud.
     */
    public function deleteGroup(int $groupId): bool
    {
        $group = Group::find($groupId);
        if (! $group) {
            return false;
        }

        // Delete from Z2 cloud first (requires z2_group_id and groupName)
        if ($group->z2_group_id) {
            $response = $this->client->request('POST', '/User/delGroup', [
                'userName' => $this->client->username,
                'groupId' => $group->z2_group_id,
                'groupName' => $group->name,
            ]);

            if (! $response || ($response['result'] ?? -1) !== 0) {
                Log::error('[Z2] Delete group from cloud failed', [
                    'groupId' => $groupId,
                    'z2_group_id' => $group->z2_group_id,
                    'response' => $response,
                ]);
                return false;
            }
        }

        // Move all devices to no group first
        Device::where('group_id', $groupId)->update(['group_id' => null]);

        // Delete locally
        $group->delete();

        return true;
    }

    /**
     * Assign device to group.
     */
    public function assignDevice(int $groupId, string $mac): bool
    {
        $group = Group::find($groupId);
        if (! $group) {
            return false;
        }

        $response = $this->client->request('POST', '/User/updateDeviceGroup', [
            'userName' => $this->client->username,
            'deviceId' => $mac,
            'groupId' => $group->z2_group_id ?? $groupId,
        ]);

        if ($response && ($response['result'] ?? -1) === 0) {
            Device::where('mac_address', $mac)->update(['group_id' => $groupId]);
            return true;
        }

        Log::error('[Z2] Assign device to group failed', ['mac' => $mac, 'groupId' => $groupId, 'response' => $response]);
        return false;
    }

    /**
     * Remove device from group.
     */
    public function removeDevice(int $groupId, string $mac): bool
    {
        Device::where('mac_address', $mac)->where('group_id', $groupId)->update(['group_id' => null]);
        return true;
    }

    /**
     * Power on all devices in group.
     */
    public function powerOnGroup(int $groupId): bool
    {
        $devices = Device::where('group_id', $groupId)->get();
        $success = true;

        foreach ($devices as $device) {
            $result = $this->client->request('POST', '/User/devicePower', [
                'userName' => $this->client->username,
                'deviceId' => $device->mac_address,
                'devicePowerOn' => 1,
            ]);

            if (! $result || ($result['result'] ?? -1) !== 0) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Power off all devices in group.
     */
    public function powerOffGroup(int $groupId): bool
    {
        $devices = Device::where('group_id', $groupId)->get();
        $success = true;

        foreach ($devices as $device) {
            $result = $this->client->request('POST', '/User/devicePower', [
                'userName' => $this->client->username,
                'deviceId' => $device->mac_address,
                'devicePowerOff' => 1,
            ]);

            if (! $result || ($result['result'] ?? -1) !== 0) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Change content for all devices in group.
     */
    public function changeGroupContent(int $groupId, string $uiCode): bool
    {
        $devices = Device::where('group_id', $groupId)->get();
        $success = true;

        foreach ($devices as $device) {
            $result = $this->client->request('POST', '/User/upgradeDeviceUi', [
                'userName' => $this->client->username,
                'deviceId' => $device->mac_address,
                'uiCode' => $uiCode,
            ]);

            if (! $result || ($result['result'] ?? -1) !== 0) {
                $success = false;
            }
        }

        return $success;
    }
}
