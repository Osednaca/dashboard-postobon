<?php

namespace App\Services\Z2;

use App\Models\Device;
use App\Models\DeviceHeartbeat;
use App\Models\Group;
use App\Models\Media;
use App\Services\PrivateCloud\PrivateCloudClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Adaptador de dispositivos sobre la nube privada (fan-private-cloud).
 *
 * Mantiene la misma interfaz pública que el antiguo Z2DeviceService (nube china)
 * para no tocar los controladores, pero internamente habla con la API REST de
 * la nube privada. El identificador de dispositivo es la MAC en mayúsculas sin
 * dos puntos (mismo formato que reporta el ventilador).
 */
class Z2DeviceService
{
    private PrivateCloudClient $client;

    public function __construct(PrivateCloudClient $client)
    {
        $this->client = $client;
    }

    /**
     * Sincroniza los dispositivos de la nube privada a la base local.
     *
     * @return array<int, Device>
     */
    public function syncDevices(): array
    {
        $response = $this->client->get('/api/devices');

        if ($response === null) {
            Log::error('[PrivateCloud] Device sync aborted: API unavailable');

            return [];
        }

        $cloudDevices = $response['devices'] ?? [];

        $macAddresses = [];
        $devicesToUpsert = [];

        foreach ($cloudDevices as $data) {
            $mac = strtoupper((string) ($data['deviceId'] ?? ''));
            if ($mac === '') {
                continue;
            }

            $macAddresses[] = $mac;

            $online = (bool) ($data['online'] ?? false);
            $power = (int) ($data['power'] ?? 0);

            $devicesToUpsert[] = [
                'mac_address' => $mac,
                'name' => $data['name'] && $data['name'] !== $mac ? $data['name'] : 'Device '.$mac,
                'firmware' => (string) ($data['version'] ?? ''),
                'hardware' => (string) ($data['hardVersion'] ?? ''),
                'rpm' => isset($data['speed']) ? (float) $data['speed'] : null,
                'status' => $online ? 'online' : 'offline',
                'last_heartbeat_at' => $this->parseDateTime($data['lastSeenIso'] ?? null),
                'power_status' => $power === 1 ? 'on' : 'off',
                'bluetooth_status' => ((int) ($data['btSwitch'] ?? 0)) === 1 ? 'on' : 'off',
            ];
        }

        // Restaurar dispositivos borrados suavemente que volvieron a aparecer
        if (! empty($macAddresses)) {
            $trashed = Device::onlyTrashed()->whereIn('mac_address', $macAddresses)->get();
            foreach ($trashed as $device) {
                $device->restore();
                Log::info('[PrivateCloud] Restored soft-deleted device', ['mac' => $device->mac_address]);
            }
        }

        if (! empty($devicesToUpsert)) {
            Device::upsert(
                $devicesToUpsert,
                ['mac_address'],
                ['name', 'firmware', 'hardware', 'rpm', 'status', 'last_heartbeat_at', 'power_status', 'bluetooth_status']
            );
        }

        $synced = Device::whereIn('mac_address', $macAddresses)->get();

        // Registrar heartbeats (historial RPM)
        foreach ($synced as $device) {
            $data = null;
            foreach ($devicesToUpsert as $d) {
                if ($d['mac_address'] === $device->mac_address) {
                    $data = $d;
                    break;
                }
            }

            if ($data && isset($data['rpm'])) {
                DeviceHeartbeat::create([
                    'device_id' => $device->id,
                    'rpm' => $data['rpm'],
                    'status' => $data['status'],
                    'received_at' => now(),
                ]);
            }
        }

        Log::info('[PrivateCloud] Synced '.count($synced).' devices');

        return $synced->all();
    }

    /**
     * Detalle en vivo del dispositivo (array de la nube privada, se muestra
     * tal cual en la vista de dispositivo).
     */
    public function getDeviceDetail(string $mac): ?array
    {
        $response = $this->client->get('/api/devices/'.$this->normalizeMac($mac));

        if ($response === null || ! isset($response['device'])) {
            return null;
        }

        return $response['device'];
    }

    /**
     * Encender el dispositivo.
     */
    public function powerOn(string $mac): bool
    {
        return $this->sendPowerCommand($mac, 1);
    }

    /**
     * Apagar el dispositivo.
     */
    public function powerOff(string $mac): bool
    {
        return $this->sendPowerCommand($mac, 0);
    }

    private function sendPowerCommand(string $mac, int $power): bool
    {
        $response = $this->client->post('/api/devices/'.$this->normalizeMac($mac).'/power', ['value' => $power]);

        if ($response !== null && ($response['result'] ?? -1) === 0) {
            return true;
        }

        Log::error('[PrivateCloud] Power command failed', ['mac' => $mac, 'power' => $power, 'response' => $response]);

        return false;
    }

    public function bluetoothOn(string $mac): ?array
    {
        return $this->setBluetooth($mac, 1);
    }

    public function bluetoothOff(string $mac): ?array
    {
        return $this->setBluetooth($mac, 0);
    }

    /**
     * Bluetooth con estado deseado: la nube privada reinyecta la orden en cada
     * heartbeat (variantes BTSWITCH/BTSwitch/btswitch) hasta que el
     * dispositivo la confirma con su telemetría.
     */
    private function setBluetooth(string $mac, int $value): ?array
    {
        $response = $this->client->post('/api/devices/'.$this->normalizeMac($mac).'/bluetooth', ['value' => $value]);

        if ($response === null) {
            return null;
        }

        return ['result' => $response['result'] ?? -1];
    }

    /**
     * Quitar un video de la SD del dispositivo sin borrarlo de la biblioteca.
     * La nube privada lo entrega por heartbeat con FileDelect + PlayList
     * (combinación verificada en hardware).
     */
    public function removeVideoFromDevice(string $mac, string $filename): bool
    {
        $response = $this->client->post('/api/devices/'.$this->normalizeMac($mac).'/remove-media', ['filename' => $filename]);

        if ($response !== null && ($response['result'] ?? -1) === 0) {
            return true;
        }

        Log::error('[PrivateCloud] Remove video from device failed', ['mac' => $mac, 'filename' => $filename, 'response' => $response]);

        return false;
    }

    private function sendCommand(string $mac, string $command): ?array
    {
        $response = $this->client->post('/api/devices/'.$this->normalizeMac($mac).'/command', ['command' => $command]);

        if ($response === null) {
            return null;
        }

        return ['result' => $response['result'] ?? -1];
    }

    /**
     * Estado Bluetooth en vivo ('on'/'off' o null).
     */
    public function getBluetoothStatus(string $mac): ?string
    {
        $data = $this->getDeviceDetail($mac);

        if ($data === null) {
            return null;
        }

        return ((int) ($data['btSwitch'] ?? 0)) === 1 ? 'on' : 'off';
    }

    /**
     * La nube privada no tiene "desvincular": el dispositivo deja de existir
     * cuando deja de reportar. Se devuelve true para que el flujo de borrado
     * local de la UI funcione.
     */
    public function unbindDevice(string $mac): bool
    {
        Log::info('[PrivateCloud] unbindDevice (no-op en nube privada)', ['mac' => $mac]);

        return true;
    }

    /**
     * Reproducir/asignar un video (identificado por filename) en el dispositivo.
     */
    public function changeVideo(string $mac, string $filename): bool
    {
        $cloudFilename = $this->resolveCloudFilename($mac, $filename);
        $response = $this->client->post('/api/devices/'.$this->normalizeMac($mac).'/play', ['filename' => $cloudFilename]);

        if ($response !== null && ($response['result'] ?? -1) === 0) {
            return true;
        }

        Log::error('[PrivateCloud] Change video failed', ['mac' => $mac, 'filename' => $cloudFilename, 'response' => $response]);

        return false;
    }

    /**
     * Resolve legacy local media to a filename known by the private cloud.
     *
     * Older records can contain paths such as media/hash.mp4 because the
     * upload controller falls back to local storage when the cloud upload
     * fails. Migrate that file on first assignment and update the record so
     * subsequent assignments use the cloud filename directly.
     */
    public function resolveCloudFilename(string $mac, string $filename): string
    {
        if (! str_contains($filename, '/') && ! str_contains($filename, '\\')) {
            return $filename;
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($filename)) {
            return basename($filename);
        }

        $media = Media::where('file_path', $filename)->first();
        $uploadName = $media?->original_name ?: basename($filename);
        $response = $this->client->postFile(
            '/api/devices/'.$this->normalizeMac($mac).'/upload',
            $disk->path($filename),
            $uploadName,
            ['assign' => 'false'],
        );

        $cloudFilename = (string) ($response['filename'] ?? '');
        if ($response !== null && ($response['result'] ?? -1) === 0 && $cloudFilename !== '') {
            Media::where('file_path', $filename)->update(['file_path' => $cloudFilename]);
            $disk->delete($filename);
            Log::info('[PrivateCloud] Legacy local media migrated to cloud', [
                'from' => $filename,
                'to' => $cloudFilename,
            ]);

            return $cloudFilename;
        }

        Log::error('[PrivateCloud] Legacy local media migration failed', [
            'filename' => $filename,
            'response' => $response,
        ]);

        return basename($filename);
    }

    /**
     * Formatear la SD del dispositivo (borra todos sus videos, sigue vinculado).
     * La nube privada lo entrega con NeedFormatSdCard=1 en el heartbeat (el
     * equivalente del /User/needFormatSd de la nube china) hasta que el
     * dispositivo confirme con SdCardEmpty=1.
     */
    public function formatSd(string $mac): bool
    {
        $response = $this->client->post('/api/devices/'.$this->normalizeMac($mac).'/format-sd');

        if ($response !== null && ($response['result'] ?? -1) === 0) {
            return true;
        }

        Log::error('[PrivateCloud] Format SD failed', ['mac' => $mac, 'response' => $response]);

        return false;
    }

    public function formatSdMultiple(array $macs): array
    {
        $results = [];
        foreach ($macs as $mac) {
            $results[$mac] = $this->formatSd($mac);
        }

        return $results;
    }

    /**
     * Lectura de un parámetro del dispositivo desde la telemetría en vivo.
     */
    public function getDeviceSetting(string $mac, string $parameter = 'Volume'): ?array
    {
        $data = $this->getDeviceDetail($mac);

        if ($data === null) {
            return null;
        }

        $map = [
            'Volume' => 'volume',
            'BTSwitch' => 'btSwitch',
            'Luminance' => 'luminance',
            'Angle' => 'angle',
        ];

        $field = $map[$parameter] ?? strtolower($parameter);
        if (! array_key_exists($field, $data)) {
            return null;
        }

        return [$parameter => (string) $data[$field]];
    }

    public function getVolume(string $mac): ?int
    {
        $data = $this->getDeviceDetail($mac);

        if ($data === null || ! isset($data['volume'])) {
            return null;
        }

        return (int) $data['volume'];
    }

    /**
     * Ajustar un parámetro (volumen / bluetooth) en el dispositivo.
     */
    public function setDeviceSetting(string $mac, string $parameter, string $value): ?array
    {
        if ($parameter === 'Volume') {
            return $this->setVolume($mac, (int) $value);
        }

        if ($parameter === 'BTSwitch') {
            return $value === '1' ? $this->bluetoothOn($mac) : $this->bluetoothOff($mac);
        }

        return $this->sendCommand($mac, $parameter.'='.$value);
    }

    /**
     * Ajustar el volumen (0-100). Devuelve ['result' => 0] en éxito para
     * compatibilidad con el controlador.
     */
    public function setVolume(string $mac, int $volume): ?array
    {
        $volume = max(0, min(100, $volume));

        $response = $this->client->post('/api/devices/'.$this->normalizeMac($mac).'/volume', ['value' => $volume]);

        if ($response === null) {
            return null;
        }

        return ['result' => $response['result'] ?? -1];
    }

    /**
     * Mover dispositivo a grupo (los grupos son locales en la nube privada).
     */
    public function moveToGroup(string $mac, int $groupId): bool
    {
        $group = Group::find($groupId);
        if (! $group) {
            return false;
        }

        Device::where('mac_address', $mac)->update(['group_id' => $groupId]);

        return true;
    }

    /**
     * Normaliza una MAC al formato de la nube privada (mayúsculas sin dos puntos).
     */
    private function normalizeMac(string $mac): string
    {
        return strtoupper(str_replace(':', '', trim($mac)));
    }

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
}
