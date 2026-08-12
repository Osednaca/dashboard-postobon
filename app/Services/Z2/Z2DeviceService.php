<?php

namespace App\Services\Z2;

use App\Models\Device;
use App\Models\DeviceHeartbeat;
use App\Models\Group;
use Illuminate\Support\Carbon;
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

        if (! $groupResponse || ! array_key_exists('aaData', $groupResponse)) {
            Log::error('[Z2] Device sync aborted: group list unavailable', ['response' => $groupResponse]);

            return [];
        }

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
        $cloudFetchComplete = true;

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
                $cloudFetchComplete = false;

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
                        $group = Group::withTrashed()->where('name', $deviceData['groupName'])->first();
                        if ($group) {
                            if ($group->trashed()) {
                                $group->restore();
                            }
                        } else {
                            $group = Group::create([
                                'name' => $deviceData['groupName'],
                                'description' => 'Grupo sincronizado desde Z2 Cloud',
                            ]);
                        }
                        $groupMap[$deviceData['groupName']] = $group->id;
                    }
                    $groupId = $groupMap[$deviceData['groupName']];
                }

                $devicesToUpsert[] = [
                    'mac_address' => $mac,
                    'name' => $deviceData['deviceName'] ?? 'Device '.$mac,
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

        // Remove local devices that are no longer linked to the cloud.
        // Only reconcile when every cloud request succeeded, otherwise a
        // transient API failure could remove the entire local inventory.
        if ($cloudFetchComplete) {
            $query = Device::query();
            if (! empty($macAddresses)) {
                $query->whereNotIn('mac_address', $macAddresses);
            }
            $removed = $query->delete();
            Log::info('[Z2] Removed devices absent from cloud', ['count' => $removed]);
        }

        Log::info('[Z2] Synced '.count($syncedDevices).' devices from cloud');

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
     * Turn the device's Bluetooth on.
     *
     * Bluetooth is exposed as a device setting (same write endpoint used for
     * volume). The parameter name may differ per Z2 build — adjust if the
     * cloud rejects it.
     */
    public function bluetoothOn(string $mac): ?array
    {
        return $this->setDeviceSetting($mac, 'Bluetooth', '1');
    }

    /**
     * Turn the device's Bluetooth off.
     */
    public function bluetoothOff(string $mac): ?array
    {
        return $this->setDeviceSetting($mac, 'Bluetooth', '0');
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
     * Query a setting from the device (e.g. Volume).
     *
     * POST /User/selectDeviceSetting
     *   userName, deviceId, parameter
     *   Success: {"result":0,"aaData":{"Volume":"50","MacIpAddress":"9097D5E4D9FC"}}
     */
    public function getDeviceSetting(string $mac, string $parameter = 'Volume'): ?array
    {
        $response = $this->client->request('POST', '/User/selectDeviceSetting', [
            'userName' => $this->client->username,
            'deviceId' => $mac,
            'parameter' => $parameter,
        ]);

        if ($response && ($response['result'] ?? -1) === 0 && isset($response['aaData'])) {
            return $response['aaData'];
        }

        Log::error('[Z2] Get device setting failed', ['mac' => $mac, 'parameter' => $parameter, 'response' => $response]);

        return null;
    }

    /**
     * Convenience method to get device volume level.
     */
    public function getVolume(string $mac): ?int
    {
        $data = $this->getDeviceSetting($mac, 'Volume');
        if ($data && isset($data['Volume'])) {
            return (int) $data['Volume'];
        }

        return null;
    }

    /**
     * Candidate write endpoints for device settings.
     *
     * The read endpoint is /User/selectDeviceSetting. The matching write
     * endpoint name varies between Z2 server builds (the original
     * /User/setDeviceSetting returns 404 on this server). We probe them in
     * order and use the first that answers with valid JSON. Simplify to a
     * single endpoint once the correct one is confirmed via the logs.
     */
    private const DEVICE_SETTING_WRITE_ENDPOINTS = [
        '/User/updateDeviceSetting',
        '/User/saveDeviceSetting',
        '/User/editDeviceSetting',
        '/User/setDeviceSetting',
        '/User/deviceSetting',
    ];

    /**
     * Set a device setting parameter in Z2 Cloud (e.g. Volume, Bluetooth).
     *
     * Probes candidate write endpoints and stops at the first that returns
     * valid JSON (success or API error). A 404/non-JSON response means the
     * endpoint does not exist, so the next candidate is tried.
     */
    public function setDeviceSetting(string $mac, string $parameter, string $value): ?array
    {
        Log::info('[Z2] Setting device parameter', ['mac' => $mac, 'parameter' => $parameter, 'value' => $value]);

        $payload = [
            'userName' => $this->client->username,
            'deviceId' => $mac,
            'parameter' => $parameter,
            'value' => $value,
        ];

        $lastResponse = null;

        foreach (self::DEVICE_SETTING_WRITE_ENDPOINTS as $endpoint) {
            $response = $this->client->request('POST', $endpoint, $payload);
            $lastResponse = $response;

            // Valid JSON response (success or API error) -> endpoint exists; stop probing.
            if ($response && ! isset($response['_error'])) {
                if (($response['result'] ?? -1) === 0) {
                    Log::info('[Z2] Device parameter updated', ['endpoint' => $endpoint, 'mac' => $mac, 'parameter' => $parameter, 'value' => $value]);
                } else {
                    Log::warning('[Z2] Device setting endpoint responded with error', ['endpoint' => $endpoint, 'response' => $response]);
                }

                return $response;
            }

            // Non-JSON (e.g. 404 HTML) -> endpoint does not exist; try the next candidate.
            Log::warning('[Z2] Device setting endpoint unavailable, trying next', ['endpoint' => $endpoint, 'response' => $response]);
        }

        return $lastResponse;
    }

    /**
     * Set device volume (0 to 100).
     */
    public function setVolume(string $mac, int $volume): ?array
    {
        $volume = max(0, min(100, $volume));

        return $this->setDeviceSetting($mac, 'Volume', (string) $volume);
    }

    /**
     * Format SD cards for multiple devices.
     * Returns an array of results keyed by MAC address.
     */
    public function formatSdMultiple(array $macs): array
    {
        $results = [];
        foreach ($macs as $mac) {
            $results[$mac] = $this->formatSd($mac);
        }

        return $results;
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
        // `runStatus` reflects cloud connectivity. The Z2 API returns
        // 0 when the device is disconnected and a non-zero value
        // (1 = running, 10 = active/normal) when it is online.
        // `devicePower` is intentionally NOT used here: the power
        // command is unreliable (returns DeviceResult 82 without
        // changing state), so it cannot be trusted as an online signal.
        $runStatus = (int) ($deviceData['runStatus'] ?? 0);

        if ($runStatus === 0) {
            return 'offline';
        }

        return 'online';
    }

    /**
     * Parse Z2 datetime string.
     */
    private function parseDateTime(?string $dateTime): ?Carbon
    {
        if (! $dateTime) {
            return null;
        }

        try {
            return Carbon::parse($dateTime);
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
