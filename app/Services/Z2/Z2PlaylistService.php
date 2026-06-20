<?php

namespace App\Services\Z2;

use App\Models\Device;
use Illuminate\Support\Facades\Log;

class Z2PlaylistService
{
    private FanCloudService $client;

    public function __construct(FanCloudService $client)
    {
        $this->client = $client;
    }

    /**
     * Get device playlist from cloud.
     */
    public function getDevicePlaylist(string $mac): array
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
                        $playlist = $deviceData['playList'] ?? '';
                        return $playlist
                            ? array_values(array_filter(explode(',', $playlist), fn($s) => $s !== ''))
                            : [];
                    }
                }
            }
        }

        return [];
    }

    /**
     * Assign video to device playlist.
     */
    public function assignVideoToDevice(string $mac, string $uiCode): bool
    {
        $response = $this->client->request('POST', '/User/upgradeDeviceUi', [
            'userName' => $this->client->username,
            'deviceId' => $mac,
            'uiCode' => $uiCode,
        ]);

        if ($response && ($response['result'] ?? -1) === 0) {
            $deviceResult = $response['DeviceResult'] ?? [];
            $deviceMac = str_replace(':', '', $mac);
            if (isset($deviceResult[$deviceMac]) && $deviceResult[$deviceMac] === 66) {
                return true;
            }
        }

        Log::error('[Z2] Assign video to device failed', ['mac' => $mac, 'uiCode' => $uiCode, 'response' => $response]);
        return false;
    }

    /**
     * Assign video to all devices in group.
     */
    public function assignVideoToGroup(int $groupId, string $uiCode): bool
    {
        $devices = Device::where('group_id', $groupId)->get();
        $success = true;

        foreach ($devices as $device) {
            $result = $this->assignVideoToDevice($device->mac_address, $uiCode);
            if (! $result) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Get currently playing media on a device.
     *
     * Uses POST /User/displayImageId to retrieve the active display.
     * Returns array with 'displayImageId' (filename) and 'playingCount'.
     */
    public function getCurrentPlaying(string $mac): ?array
    {
        $response = $this->client->request('POST', '/User/displayImageId', [
            'userName'       => $this->client->username,
            'iDisplayStart'  => 0,
            'iDisplayLength' => 40,
            'deviceId'       => $mac,
            'playings'       => '',
        ]);

        if ($response && ($response['result'] ?? -1) === 0) {
            return [
                'displayImageId' => $response['displayImageId'] ?? null,
                'playingCount'   => $response['playingCount'] ?? '0',
            ];
        }

        Log::error('[Z2] Get current playing failed', ['mac' => $mac, 'response' => $response]);
        return null;
    }
}
