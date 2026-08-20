<?php

namespace App\Services\Z2;

use App\Models\Device;
use App\Services\PrivateCloud\PrivateCloudClient;
use Illuminate\Support\Facades\Log;

/**
 * Adaptador de playlists/contenido sobre la nube privada (fan-private-cloud).
 *
 * En la nube privada no existe "playlist" persistente como en Z2; el video en
 * reproducción es la telemetría displayImageId y asignar contenido equivale a
 * enviar el comando "play" con el filename.
 */
class Z2PlaylistService
{
    private PrivateCloudClient $client;

    private Z2DeviceService $deviceService;

    public function __construct(PrivateCloudClient $client, Z2DeviceService $deviceService)
    {
        $this->client = $client;
        $this->deviceService = $deviceService;
    }

    /**
     * Playlist del dispositivo desde la telemetría en vivo.
     *
     * @return array<int, string>
     */
    public function getDevicePlaylist(string $mac): array
    {
        $response = $this->client->get('/api/devices/'.$this->normalizeMac($mac));

        if ($response === null || ! isset($response['device'])) {
            return [];
        }

        $playlist = $response['device']['playlist'] ?? [];

        return is_array($playlist) ? array_values(array_filter($playlist, fn ($f) => $f !== '')) : [];
    }

    /**
     * Asignar video (filename) a un dispositivo.
     */
    public function assignVideoToDevice(string $mac, string $uiCode): bool
    {
        $filename = $this->deviceService->resolveCloudFilename($mac, $uiCode);
        $response = $this->client->post('/api/devices/'.$this->normalizeMac($mac).'/play', ['filename' => $filename]);

        if ($response !== null && ($response['result'] ?? -1) === 0) {
            return true;
        }

        Log::error('[PrivateCloud] Assign video to device failed', ['mac' => $mac, 'uiCode' => $filename, 'response' => $response]);

        return false;
    }

    /**
     * Asignar video a todos los dispositivos de un grupo.
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
     * Obtener el video en reproducción actual en el dispositivo.
     *
     * @return array{displayImageId: string|null, playingCount: string}|null
     */
    public function getCurrentPlaying(string $mac): ?array
    {
        $response = $this->client->get('/api/devices/'.$this->normalizeMac($mac));

        if ($response === null || ! isset($response['device'])) {
            return null;
        }

        $data = $response['device'];

        return [
            'displayImageId' => $data['displayImageId'] ?? null,
            'playingCount' => '1',
        ];
    }

    private function normalizeMac(string $mac): string
    {
        return strtoupper(str_replace(':', '', trim($mac)));
    }
}
