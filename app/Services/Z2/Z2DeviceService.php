<?php

namespace App\Services\Z2;

use App\Models\Device;
use App\Models\DeviceHeartbeat;
use App\Models\Group;
use Illuminate\Support\Facades\Log;

class Z2DeviceService
{
    private FanCloudService $client;

    public function __construct(FanCloudService $client)
    {
        $this->client = $client;
    }

    /**
     * Fetch all devices from cloud and sync to local database.
     */
    public function syncDevices(): array
    {
        // Step 1: Get group list
        $groupResponse = $this->client->request('POST', '/User/groupList', [
            'userName' => $this->client->username,
        ]);

        $groupIds = [];
        if ($groupResponse && isset($groupResponse['aaData'])) {
            foreach ($groupResponse['aaData'] as $group) {
                $groupIds[] = $group['idGroup'] ?? 0;
            }
        }
        // Also check ungrouped devices
        $groupIds[] = 0;

        $devicesToUpsert = [];
        $macAddresses = [];
        $groupMap = [];

        // Step 2: For each group, fetch devices
        foreach ($groupIds as $groupId) {
            $response = $this->client->request('POST', '/User/groupDeviceList', [
                'userName' => $this->client->username,
                'iDisplayStart' => 0,
                'iDisplayLength' => 50,
                'deviceCode' => '',
                'groupID' => $groupId,
            ]);

            if (! $response || ! isset($response['aaData'])) {
                continue;
            }

            foreach ($response['aaData'] as $deviceData) {
                $mac = $deviceData['macIpAddress'] ?? null;
                if (! $mac) {
                    continue;
                }

                // Skip if already synced
                if (in_array($mac, $macAddresses)) {
                    continue;
                }

                $macAddresses[] = $mac;

                // Map Z2 status to local status
                $status = $this->mapStatus($deviceData);
                $powerStatus = ($deviceData['devicePower'] ?? 0) === 1 ? 'on' : 'off';

                // Find or create group
                $groupId = null;
                if (! empty($deviceData['groupName'])) {
                    if (! isset($groupMap[$deviceData['groupName']])) {
                        $group = Group::firstOrCreate(
                            ['name' => $deviceData['groupName']],
                            ['description' => 'Grupo sincronizado desde Z2 Cloud']
                        );
                        $groupMap[$deviceData['groupName']] = $group->id;
                    }
                    $groupId = $groupMap[$deviceData['groupName']];
                }

                $devicesToUpsert[] = [
                    'mac_address' => $mac,
                    'name' => $deviceData['deviceName'] ?? 'Device ' . $mac,
                    'firmware' => (string) ($deviceData['sysVersion'] ?? ''),
                    'hardware' => (string) ($deviceData['hardVersion'] ?? ''),
                    'rpm' => isset($deviceData['speed']) ? (float) $deviceData['speed'] : null,
                    'status' => $status,
                    'group_id' => $groupId,
                    'last_heartbeat_at' => $this->parseDateTime($deviceData['lastHeartDate'] ?? null),
                    'working_hours' => isset($deviceData['workTime']) ? (float) $deviceData['workTime'] : 0,
                    'power_status' => $powerStatus,
                ];
            }
        }

        // Restore soft-deleted devices that are back in the cloud
        if (! empty($macAddresses)) {
            $trashedDevices = Device::onlyTrashed()->whereIn('mac_address', $macAddresses)->get();
            foreach ($trashedDevices as $trashedDevice) {
                $trashedDevice->restore();
                Log::info('[Z2] Restored soft-deleted device', ['mac' => $trashedDevice->mac_address]);
            }
        }

        // Atomic upsert to avoid race conditions
        if (! empty($devicesToUpsert)) {
            Device::upsert(
                $devicesToUpsert,
                ['mac_address'],
                ['name', 'firmware', 'hardware', 'rpm', 'status', 'group_id', 'last_heartbeat_at', 'working_hours', 'power_status']
            );
        }

        // Fetch synced devices and sync heartbeats
        $syncedDevices = Device::whereIn('mac_address', $macAddresses)->get();

        foreach ($syncedDevices as $device) {
            // Record heartbeat
            $deviceData = null;
            foreach ($devicesToUpsert as $d) {
                if ($d['mac_address'] === $device->mac_address) {
                    $deviceData = $d;
                    break;
                }
            }

            if ($deviceData && isset($deviceData['rpm'])) {
                DeviceHeartbeat::create([
                    'device_id' => $device->id,
                    'rpm' => $deviceData['rpm'],
                    'status' => $deviceData['status'],
                    'received_at' => now(),
                ]);
            }
        }

        // Mark devices not in cloud as offline
        Device::whereNotIn('mac_address', $macAddresses)
            ->where('status', '!=', 'offline')
            ->update(['status' => 'offline']);

        Log::info('[Z2] Synced ' . count($syncedDevices) . ' devices from cloud');

        return $syncedDevices->all();
    }

    /**
     * Get device detail from cloud.
     */
    public function getDeviceDetail(string $mac): ?array
    {
        // Get group list first
        $groupResponse = $this->client->request('POST', '/User/groupList', [
            'userName' => $this->client->username,
        ]);

        $groupIds = [0]; // Always check ungrouped
        if ($groupResponse && isset($groupResponse['aaData'])) {
            foreach ($groupResponse['aaData'] as $group) {
                $groupIds[] = $group['idGroup'] ?? 0;
            }
        }

        // Search in each group
        foreach ($groupIds as $groupId) {
            $response = $this->client->request('POST', '/User/groupDeviceList', [
                'userName' => $this->client->username,
                'iDisplayStart' => 0,
                'iDisplayLength' => 50,
                'deviceCode' => '',
                'groupID' => $groupId,
            ]);

            if ($response && isset($response['aaData'])) {
                foreach ($response['aaData'] as $deviceData) {
                    if (($deviceData['macIpAddress'] ?? '') === $mac) {
                        return $deviceData;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Power on device.
     */
    public function powerOn(string $mac): bool
    {
        return $this->sendPowerCommand($mac, 1);
    }

    /**
     * Power off device.
     */
    public function powerOff(string $mac): bool
    {
        return $this->sendPowerCommand($mac, 0);
    }

    /**
     * Send power command to device.
     */
    private function sendPowerCommand(string $mac, int $power): bool
    {
        $param = $power === 1 ? 'devicePowerOn' : 'devicePowerOff';

        $response = $this->client->request('POST', '/User/devicePower', [
            'userName' => $this->client->username,
            'deviceId' => $mac,
            $param => 1,
        ]);

        if ($response && ($response['result'] ?? -1) === 0) {
            $deviceResult = $response['DeviceResult'] ?? [];
            $deviceMac = str_replace(':', '', $mac);
            if (isset($deviceResult[$deviceMac]) && $deviceResult[$deviceMac] >= 0) {
                // Command sent successfully to cloud
                // Note: We do NOT update local DB here because the device state
                // will only change after the device responds. The next sync
                // will update the real state from the cloud.
                return true;
            }
        }

        Log::error('[Z2] Power command failed', ['mac' => $mac, 'power' => $power, 'response' => $response]);
        return false;
    }

    /**
     * Unbind device from account.
     */
    public function unbindDevice(string $mac): bool
    {
        $response = $this->client->request('POST', '/User/unbindDevice', [
            'userName' => $this->client->username,
            'deviceId' => $mac,
        ]);

        if ($response && ($response['result'] ?? -1) === 0) {
            $deviceResult = $response['DeviceResult'] ?? [];
            $deviceMac = str_replace(':', '', $mac);
            if (isset($deviceResult[$deviceMac]) && $deviceResult[$deviceMac] >= 0) {
                return true;
            }
        }

        Log::error('[Z2] Unbind failed', ['mac' => $mac, 'response' => $response]);
        return false;
    }

    public function changeVideo(string $mac, string $uiCode): bool
    {
        $response = $this->client->request('POST', '/User/upgradeDeviceUi', [
            'userName' => $this->client->username,
            'deviceId' => $mac,
            'displayImageId' => $uiCode,
        ]);

        if ($response && ($response['result'] ?? -1) === 0) {
            $deviceResult = $response['DeviceResult'] ?? [];
            $deviceMac = str_replace(':', '', $mac);
            if (isset($deviceResult[$deviceMac]) && $deviceResult[$deviceMac] === 66) {
                return true;
            }
        }

        Log::error('[Z2] Change video failed', ['mac' => $mac, 'uiCode' => $uiCode, 'response' => $response]);
        return false;
    }

    /**
     * Format the device's SD card, clearing all videos from the playlist.
     *
     * This does NOT unbind the device from the account — it only wipes the
     * SD card contents. The device remains associated and can receive new
     * video assignments immediately after.
     *
     * POST /User/needFormatSd
     *   userName, deviceId, needFormatSd=1
     *   Success: {"result":0,"DeviceResult":{"MAC":66}}
     */
    public function formatSd(string $mac): bool
    {
        Log::info('[Z2] Formatting SD card', ['mac' => $mac]);

        $response = $this->client->request('POST', '/User/needFormatSd', [
            'userName' => $this->client->username,
            'deviceId' => $mac,
            'needFormatSd' => 1,
        ]);

        if ($response && ($response['result'] ?? -1) === 0) {
            $deviceResult = $response['DeviceResult'] ?? [];
            $deviceMac = str_replace(':', '', $mac);
            if (isset($deviceResult[$deviceMac]) && $deviceResult[$deviceMac] === 66) {
                Log::info('[Z2] SD card format command sent successfully', ['mac' => $mac]);
                return true;
            }
        }

        Log::error('[Z2] Format SD failed', ['mac' => $mac, 'response' => $response]);
        return false;
    }

    /**
     * Move device to group.
     */
    public function moveToGroup(string $mac, int $groupId): bool
    {
        $group = Group::find($groupId);
        if (! $group) {
            return false;
        }

        $response = $this->client->request('POST', '/User/updateDeviceGroup', [
            'userName' => $this->client->username,
            'deviceId' => $mac,
            'groupId' => $groupId,
        ]);

        if ($response && ($response['result'] ?? -1) === 0) {
            Device::where('mac_address', $mac)->update(['group_id' => $groupId]);
            return true;
        }

        Log::error('[Z2] Move to group failed', ['mac' => $mac, 'groupId' => $groupId, 'response' => $response]);
        return false;
    }

    /**
     * Map Z2 device status to local status.
     */
    private function mapStatus(array $deviceData): string
    {
        $devicePower = $deviceData['devicePower'] ?? 0;
        $runStatus = $deviceData['runStatus'] ?? 0;

        if ($devicePower === 0) {
            return 'offline';
        }

        if ($runStatus === 10) {
            return 'online';
        }

        if ($runStatus === 0) {
            return 'disabled';
        }

        return 'maintenance';
    }

    /**
     * Parse Z2 datetime string.
     */
    private function parseDateTime(?string $dateTime): ?\Illuminate\Support\Carbon
    {
        if (! $dateTime) {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($dateTime);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Sync device playlist from cloud.
     */
    private function syncDevicePlaylist(Device $device, string $playlist): void
    {
        // Store playlist in device or sync with media library
        // This is a placeholder for playlist synchronization
        Log::info('[Z2] Device playlist synced', ['device' => $device->mac_address, 'playlist' => $playlist]);
    }
}
