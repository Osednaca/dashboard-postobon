<?php

namespace App\Services\Fleet;

use App\Models\AuditLog;
use App\Models\Device;
use App\Models\Media;
use App\Models\Wl35DeviceMedia;
use App\Models\Wl35DeviceProfile;
use App\Services\Z2\Z2DeviceService;
use App\Services\Z2\Z2VideoService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class DeviceVideoRemovalService
{
    public function __construct(
        private readonly Z2DeviceService $devices,
        private readonly Z2VideoService $videos,
        private readonly UnifiedFleetClient $fleet,
    ) {}

    public function remove(Media $media, array $keys, ?int $groupId, int $userId): array
    {
        // Resolve the entire selection before sending even the first command.
        $targets = [];
        $identities = [];
        foreach ($keys as $key) {
            [$type, $id] = explode(':', $key, 2);
            $device = $type === 'z2' ? Device::find($id) : Wl35DeviceProfile::where('device_id', $id)->first();
            if ($device === null || ($groupId !== null && (int) $device->group_id !== $groupId)) {
                throw ValidationException::withMessages(['device_keys' => 'La selección cambió: un dispositivo ya no existe o no pertenece al grupo. Actualiza la lista.']);
            }
            $identity = $type === 'z2' ? strtoupper(preg_replace('/[^a-fA-F0-9]/', '', $device->mac_address)) : $id;
            if (($type === 'z2' && strlen($identity) !== 12) || in_array($type.':'.$identity, $identities, true)) {
                throw ValidationException::withMessages(['device_keys' => 'La selección contiene un dispositivo duplicado o sin una identificación válida.']);
            }
            $identities[] = $type.':'.$identity;
            $targets[] = compact('key', 'type', 'device');
        }

        $results = [];
        foreach ($targets as $target) {
            $device = $target['device'];
            $status = 'failed';
            $warning = null;
            try {
                if ($target['type'] === 'z2') {
                    $filename = $this->videos->cloudFilename($media->file_path);
                    if ($filename === null) {
                        throw new VideoRemovalRejected('Este video no tiene una identificación vinculada al equipo 42. Revísalo en el detalle del dispositivo.');
                    }
                    if (! $this->devices->removeVideoFromDevice($device->mac_address, $filename)) {
                        throw new VideoRemovalRejected('El equipo 42 no aceptó la solicitud de quitar el video.');
                    }
                } else {
                    $warning = $this->removeWl35($device->device_id, $media->id);
                }
                $status = 'accepted';
                $message = $warning ?? 'Solicitud aceptada. El borrado físico aún no está verificado.';
            } catch (Throwable $exception) {
                Log::warning('No fue posible solicitar el retiro de un video.', ['key' => $target['key'], 'media_id' => $media->id, 'error' => $exception->getMessage()]);
                $message = $exception instanceof VideoRemovalRejected
                    ? $exception->getMessage() : 'No fue posible procesar la solicitud para este dispositivo.';
            }
            $result = ['key' => $target['key'], 'name' => $device->name ?: $target['key'], 'status' => $status, 'message' => $message];
            $results[] = $result;
            try {
                AuditLog::create([
                    'user_id' => $userId, 'action' => 'remove_video',
                    'entity_type' => $target['type'] === 'z2' ? 'Device' : 'Wl35DeviceProfile',
                    'entity_id' => $device->id,
                    'details' => $result + ['media_id' => $media->id, 'group_id' => $groupId],
                    'ip_address' => request()->ip(), 'user_agent' => request()->userAgent(),
                ]);
            } catch (Throwable $exception) {
                Log::error('No se pudo guardar la auditoría del retiro de video.', ['result' => $result, 'error' => $exception->getMessage()]);
            }
        }

        return $results;
    }

    private function removeWl35(string $deviceId, int $mediaId): ?string
    {
        $lock = Cache::lock('device-video-removal:wl35:'.hash('sha256', $deviceId), max(120, (int) config('unifiedfleet.timeout', 30) * 4));
        if (! $lock->get()) {
            throw new VideoRemovalRejected('Hay otra solicitud de quitar video en curso para este dispositivo. Espera a que termine.');
        }

        try {
            DeviceMutationLock::assertNoCampaignSession(['wl35:'.$deviceId]);
            $mappings = Wl35DeviceMedia::where('device_id', $deviceId)->where('media_id', $mediaId)->get();
            if ($mappings->count() !== 1) {
                throw new VideoRemovalRejected('No se puede identificar este video en el equipo 35. Revísalo en el detalle del dispositivo.');
            }
            $index = $mappings->first()->video_index;
            $live = collect($this->gatewayCall(fn () => $this->fleet->getFleet())['devices'] ?? [])->first(
                fn ($item) => ($item['type'] ?? null) === 'wl35' && ($item['id'] ?? null) === $deviceId
            );
            if ($live === null || ($live['session_ready'] ?? false) !== true || ($live['uploading'] ?? false) === true
                || $index < 1 || $index > 255 || $index > (int) ($live['video_count'] ?? 0)) {
                throw new VideoRemovalRejected('El equipo 35 no está listo o el índice del video ya no coincide. Actualiza su contenido antes de reintentar.');
            }
            try {
                $response = $this->fleet->deleteWl35Video($deviceId, $index, (int) $live['video_count']);
                if (($response['success'] ?? null) !== true || ($response['index'] ?? null) !== $index) {
                    throw new VideoRemovalRejected('Respuesta de eliminación no confirmada.');
                }
            } catch (Throwable $exception) {
                // The command may already have renumbered the device even when its ACK was lost.
                $this->invalidateWl35($deviceId);
                throw new VideoRemovalRejected('No se pudo confirmar la solicitud. No repitas la orden: revisa el contenido del dispositivo antes de continuar.', previous: $exception);
            }
            try {
                DB::transaction(function () use ($deviceId, $index): void {
                    Wl35DeviceMedia::where('device_id', $deviceId)->where('video_index', $index)->delete();
                    Wl35DeviceMedia::where('device_id', $deviceId)->where('video_index', '>', $index)
                        ->orderBy('video_index')->get()
                        ->each(fn (Wl35DeviceMedia $mapping) => $mapping->decrement('video_index'));
                });
            } catch (Throwable $exception) {
                Log::error('Solicitud aceptada pero no se pudo actualizar el registro WL35.', ['device_id' => $deviceId, 'error' => $exception->getMessage()]);
                // Clear uncertain library identities instead of risking a second removal at a stale index.
                $this->invalidateWl35($deviceId);

                return 'Solicitud aceptada, pero no se pudo actualizar el registro local. No repitas la orden: revisa el contenido del dispositivo antes de continuar.';
            }

            return null;
        } finally {
            try {
                $lock->release();
            } catch (Throwable $exception) {
                Log::error('No se pudo liberar el bloqueo de retiro de video.', ['device_id' => $deviceId, 'error' => $exception->getMessage()]);
            }
        }
    }

    private function invalidateWl35(string $deviceId): void
    {
        try {
            Wl35DeviceMedia::where('device_id', $deviceId)->update(['media_id' => null, 'filename' => null]);
        } catch (Throwable $exception) {
            Log::error('No fue posible invalidar los índices WL35.', ['device_id' => $deviceId, 'error' => $exception->getMessage()]);
        }
    }

    private function gatewayCall(callable $operation): array
    {
        try {
            return $operation();
        } catch (Throwable $exception) {
            throw new VideoRemovalRejected('No fue posible comunicarse con el gateway 35. Revisa la conexión antes de reintentar.', previous: $exception);
        }
    }
}
