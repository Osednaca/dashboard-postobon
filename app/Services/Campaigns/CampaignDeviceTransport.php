<?php

namespace App\Services\Campaigns;

use App\Models\CampaignBaselineFile;
use App\Models\CampaignDeviceFile;
use App\Models\CampaignDevicePlayback;
use App\Models\Wl35DeviceMedia;
use App\Services\Fleet\MediaPreviewSource;
use App\Services\Fleet\MediaSourceMaterializer;
use App\Services\Fleet\UnifiedFleetClient;
use App\Services\Fleet\Wl35MediaMapping;
use App\Services\PrivateCloud\PrivateCloudClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/** Every method runs under the lifecycle's physical-device lock. */
class CampaignDeviceTransport
{
    public function __construct(private UnifiedFleetClient $fleet, private PrivateCloudClient $cloud, private MediaSourceMaterializer $materializer, private MediaPreviewSource $sources) {}

    /** Caller holds the device lock and verifies all backups and active references. */
    public function removeBaseline(CampaignBaselineFile $file): void
    {
        $target = CampaignDevicePlayback::findOrFail($file->playback_id)->target_key;
        $probe = new CampaignDeviceFile(['target_key' => $target]);
        $operation = $file->operation ?? [];
        if ($file->state === 'needs_review' || $file->state === 'removed') {
            return;
        }
        if (str_starts_with($target, 'z2:')) {
            $device = $this->z2($probe);
            $id = $operation['removal_id'] ?? null;
            $name = $operation['current_filename'] ?? $file->filename;
            $record = collect($device['mediaRemovals'] ?? [])->first(fn ($r) => $id && ($r['requestId'] ?? null) === $id);
            if (($record['status'] ?? null) === 'reported_absent') {
                $file->update(['state' => 'removed', 'last_error' => 'Ausencia reportada en la lista; el borrado físico de SD no está confirmado.']);

                return;
            }
            if ($file->state !== 'removing') {
                $id = (string) Str::uuid();
                $operation['removal_id'] = $id;
                $file->update(['state' => 'removing', 'operation' => $operation, 'last_error' => null]);
            }
            $response = $this->cloud->post('/api/devices/'.substr($target, 3).'/remove-media', ['filename' => $name, 'request_id' => $id]);
            if (($response['result'] ?? -1) !== 0) {
                throw new RuntimeException('No se pudo solicitar el retiro del archivo 42.');
            }

            return;
        }
        if ($file->state === 'removing') {
            $file->update(['state' => 'needs_review', 'last_error' => 'El borrado anterior del equipo 35 se interrumpió; requiere revisión.']);

            return;
        }
        $live = $this->wl35($probe);
        $id = substr($target, 5);
        $identity = $operation['current_identity'] ?? $file->upload_identity;
        $mappings = Wl35DeviceMedia::where('device_id', $id)->where('upload_identity', $identity)->get();
        $mapping = $mappings->first();
        if (! $identity || $mappings->count() !== 1 || $mapping->video_index > (int) $live['video_count']) {
            $file->update(['state' => 'needs_review', 'last_error' => 'No se puede identificar con seguridad el archivo anterior del equipo 35.']);

            return;
        }
        $index = $mapping->video_index;
        $file->update(['state' => 'removing', 'operation' => array_merge($operation, ['before_count' => (int) $live['video_count'], 'removal_index' => $index]), 'last_error' => null]);
        try {
            $result = $this->fleet->deleteWl35Video($id, $index, (int) $live['video_count']);
            if (($result['confirmed'] ?? false) !== true || ($result['deletion_status'] ?? '') !== 'confirmed' || (int) ($result['video_count'] ?? -1) !== (int) $live['video_count'] - 1) {
                throw new RuntimeException('Respuesta de borrado sin evidencia del dispositivo.');
            }
            DB::transaction(function () use ($id, $target, $index, $mapping, $file): void {
                $mapping->delete();
                Wl35DeviceMedia::where('device_id', $id)->where('video_index', '>', $index)->orderBy('video_index')->get()->each(fn ($m) => $m->decrement('video_index'));
                CampaignDeviceFile::where('target_key', $target)->where('video_index', '>', $index)->decrement('video_index');
                $file->update(['state' => 'removed', 'last_error' => null]);
            });
        } catch (Throwable $e) {
            Wl35DeviceMedia::where('device_id', $id)->update(['media_id' => null, 'filename' => null]);
            $file->update(['state' => 'needs_review', 'last_error' => 'Borrado anterior 35 ambiguo; no se repetirá el índice.']);
        }
    }

    /** $verifiedPath must be supplied by the backup verifier on each attempt. */
    public function restoreBaselineFile(CampaignBaselineFile $file, string $verifiedPath): void
    {
        if (in_array($file->state, ['backed_up', 'restored', 'needs_review'], true)) {
            return;
        }
        if (! in_array($file->state, ['removed', 'restoring'], true)) {
            throw new RuntimeException('El retiro anterior todavía no ha terminado.');
        }
        $target = CampaignDevicePlayback::findOrFail($file->playback_id)->target_key;
        $probe = new CampaignDeviceFile(['target_key' => $target]);
        $operation = $file->operation ?? [];
        if (str_starts_with($target, 'z2:')) {
            $device = $this->z2($probe);
            if ($file->state === 'restoring') {
                if (($operation['stage'] ?? null) === 'sending') {
                    $name = $operation['current_filename'] ?? null;
                    if ($name && ($device['reportedPlaylistVersion'] ?? 0) > ($operation['report_version'] ?? 0)
                        && ($device['reportedPlaylistAt'] ?? 0) > ($operation['sent_at_ms'] ?? 0)
                        && in_array($name, $device['reportedPlaylist'] ?? [], true)) {
                        $file->update(['state' => 'restored', 'last_error' => null]);
                    } elseif (($operation['sent_at_ms'] ?? 0) < now()->subMinutes(5)->getTimestampMs()) {
                        $file->update(['state' => 'needs_review', 'last_error' => 'La restauración 42 no fue reportada; requiere revisión antes de reintentar.']);
                    }

                    return;
                }
                $file->update(['state' => 'needs_review', 'last_error' => 'La preparación del respaldo 42 se interrumpió; requiere revisión.']);

                return;
            }
            $file->update(['state' => 'restoring', 'operation' => array_merge($operation, ['stage' => 'uploading_library']), 'last_error' => null]);
            try {
                $upload = $this->cloud->postFile('/api/media/upload', $verifiedPath, $file->filename);
                if (($upload['result'] ?? -1) !== 0 || ! is_string($upload['filename'] ?? null)) {
                    throw new RuntimeException('La biblioteca no confirmó la copia anterior.');
                }
                $operation['current_filename'] = $upload['filename'];
                $operation['restored_filename'] = $upload['filename'];
                $operation['stage'] = 'sending';
                $operation['sent_at_ms'] = now()->getTimestampMs();
                $operation['report_version'] = $device['reportedPlaylistVersion'] ?? 0;
                unset($operation['removal_id']);
                $file->update(['operation' => $operation]);
                $response = $this->cloud->post('/api/devices/'.substr($target, 3).'/play', ['filename' => $upload['filename']]);
                if (($response['result'] ?? -1) !== 0) {
                    $file->update(['last_error' => 'Esperando evidencia de la restauración del equipo 42.']);
                }
            } catch (Throwable $e) {
                // If the play request may have reached the provider, wait for a new report.
                if (($file->fresh()->operation['stage'] ?? null) !== 'sending') {
                    $file->update(['state' => 'needs_review', 'last_error' => 'La copia anterior 42 requiere revisión antes de reintentar.']);
                } else {
                    $file->update(['last_error' => 'Esperando evidencia de la restauración del equipo 42.']);
                }
            }

            return;
        }
        if ($file->state === 'restoring') {
            $file->update(['state' => 'needs_review', 'last_error' => 'La carga anterior del equipo 35 se interrumpió; requiere revisión.']);

            return;
        }
        $live = $this->wl35($probe);
        $id = substr($target, 5);
        $count = (int) $live['video_count'];
        $uploadName = $file->filename ?: 'campaign-baseline-'.$file->id.'.mp4';
        $file->update(['state' => 'restoring', 'operation' => array_merge($operation, ['before_count' => $count]), 'last_error' => null]);
        try {
            $result = $this->fleet->uploadPathAndDistribute($verifiedPath, $uploadName, [$target], null, false);
            $item = collect($result['results'] ?? [])->firstWhere('key', $target);
            if (! is_array($item) || ($item['success'] ?? false) !== true || (int) ($item['video_index'] ?? 0) !== $count + 1) {
                throw new RuntimeException('La carga del respaldo 35 no fue confirmada.');
            }
            DB::transaction(function () use ($id, $count, $file, $operation, $uploadName): void {
                $identity = (string) Str::uuid();
                Wl35DeviceMedia::create(['device_id' => $id, 'video_index' => $count + 1, 'media_id' => $file->media_id, 'filename' => $uploadName, 'upload_identity' => $identity]);
                $file->update(['state' => 'restored', 'operation' => array_merge($operation, ['current_identity' => $identity, 'current_index' => $count + 1, 'restored_upload_identity' => $identity, 'restored_index' => $count + 1]), 'last_error' => null]);
            });
        } catch (Throwable $e) {
            Wl35DeviceMedia::where('device_id', $id)->update(['media_id' => null, 'filename' => null]);
            $file->update(['state' => 'needs_review', 'last_error' => 'Carga anterior 35 ambigua; no se repetirá automáticamente.']);
        }
    }

    public function captureBaseline(string $target): array
    {
        $file = new CampaignDeviceFile(['target_key' => $target]);
        if (str_starts_with($target, 'z2:')) {
            $device = $this->z2($file);
            if (! is_array($device['reportedPlaylist'] ?? null)
                || ! empty($device['desiredVideo']) || (int) ($device['updateImageStatus'] ?? 0) === 1
                || array_intersect(['FileDelect', 'NeedFormatSdCard', 'PlayList', 'Play'], array_keys($device['pending'] ?? [])) !== []
                || (isset($device['playlist']) && $device['playlist'] !== $device['reportedPlaylist'])
                || collect($device['mediaRemovals'] ?? [])->contains(fn ($removal) => ($removal['status'] ?? null) !== 'reported_absent')) {
                throw new RuntimeException('Espera a que termine la operación de contenido del equipo 42 antes de iniciar la campaña.');
            }
            $files = $device['reportedPlaylist'];
            $selection = $device['displayImageId'] ?? null;

            return ['type' => 'z2', 'files' => $files, 'selection' => is_string($selection) && in_array($selection, $files, true) ? $selection : null];
        }
        $device = $this->wl35($file);
        $count = $device['video_count'] ?? null;
        if (! is_int($count) || $count < 0 || $count > 255) {
            throw new RuntimeException('El equipo 35 no informó un conteo válido para conservar su contenido.');
        }
        $selection = $device['current_video'] ?? null;
        $selection = is_int($selection) && $selection >= 1 && $selection <= $count ? $selection : null;
        $mapping = $selection === null ? null : Wl35DeviceMedia::where('device_id', substr($target, 5))->where('video_index', $selection)->first();

        return ['type' => 'wl35', 'count' => $count, 'selection' => $selection, 'upload_identity' => $mapping?->upload_identity];
    }

    /** True means the provider accepted selection; 42 may still deliver it by heartbeat. */
    public function restoreBaseline(string $target, array $baseline): bool
    {
        $file = new CampaignDeviceFile(['target_key' => $target]);
        if (str_starts_with($target, 'z2:')) {
            $device = $this->z2($file);
            if (! in_array($baseline['selection'], $device['reportedPlaylist'] ?? [], true)
                || collect($device['mediaRemovals'] ?? [])->contains(fn ($removal) => ($removal['status'] ?? null) !== 'reported_absent')) {
                throw new RuntimeException('La selección anterior no está disponible o aún hay un retiro pendiente en el equipo 42.');
            }
            $file->filename = $baseline['selection'];
            $this->select($file);

            return true;
        }
        $device = $this->wl35($file);
        $index = $baseline['selection'];
        $extra = CampaignDeviceFile::where('target_key', $target)->where('owned', true)->where('state', 'ready')->pluck('video_index')->filter()->unique()->count();
        if (($device['video_count'] ?? null) !== $baseline['count'] + $extra || $index > $baseline['count']) {
            throw new RuntimeException('El contenido del equipo 35 cambió. Revisa la selección anterior antes de recuperarla.');
        }
        if ($baseline['upload_identity'] ?? null) {
            $mapping = Wl35DeviceMedia::where('device_id', substr($target, 5))->where('video_index', $index)->first();
            if ($mapping?->upload_identity !== $baseline['upload_identity']) {
                throw new RuntimeException('La identidad de la selección anterior del equipo 35 cambió.');
            }
        }
        $result = $this->fleet->sendCommand(['command' => 'play', 'targets' => [$target], 'wl35_video_index' => $index]);
        if ((collect($result['results'] ?? [])->firstWhere('key', $target)['success'] ?? false) !== true) {
            throw new RuntimeException('El equipo 35 no confirmó la selección anterior.');
        }

        return true;
    }

    private function z2(CampaignDeviceFile $file): array
    {
        $response = $this->cloud->get('/api/devices/'.substr($file->target_key, 3));
        $device = $response['device'] ?? null;
        if (! is_array($device) || ! isset($device['reportedPlaylistAt']) || $device['reportedPlaylistAt'] < now()->subSeconds(120)->getTimestampMs()) {
            throw new RuntimeException('Se necesita un reporte reciente del contenido del equipo 42.');
        }

        return $device;
    }

    private function wl35(CampaignDeviceFile $file): array
    {
        $device = collect($this->fleet->getFleet()['devices'] ?? [])->first(fn ($d) => ($d['type'] ?? null) === 'wl35' && ($d['id'] ?? null) === substr($file->target_key, 5));
        if (! $device || ! ($device['online'] ?? false) || ! ($device['session_ready'] ?? false) || ($device['uploading'] ?? false)) {
            throw new RuntimeException('El equipo 35 no estÃ¡ disponible para procesar la campaÃ±a.');
        }

        return $device;
    }

    public function install(CampaignDeviceFile $file, ?callable $stillWanted = null): void
    {
        if (str_starts_with($file->target_key, 'z2:')) {
            $this->installZ2($file, $stillWanted);

            return;
        }
        $live = $this->wl35($file);
        $id = substr($file->target_key, 5);
        $existing = Wl35DeviceMedia::where('device_id', $id)->where('media_id', $file->media_id)->first();
        if ($existing && $existing->video_index <= (int) $live['video_count']) {
            $file->video_index = $existing->video_index;
            $owned = $this->alreadyOwned($file, $existing->upload_identity);
            $file->update(['evidence' => ['mapping_id' => $existing->id, 'upload_identity' => $existing->upload_identity], 'video_index' => $existing->video_index, 'state' => 'ready', 'owned' => $owned, 'confirmed_at' => now(), 'last_error' => null]);

            return;
        }
        $path = 'campaigns/'.Str::uuid().'.mp4';
        $this->materializer->materialize($file->media, $path);
        if ($stillWanted !== null && ! $stillWanted()) {
            Storage::disk('local')->delete($path);

            return;
        }
        // Persist intent before any irreversible upload. A crash must never silently repeat it.
        $file->update(['state' => 'installing', 'owned' => true, 'requested_at' => now(), 'evidence' => ['before_count' => (int) $live['video_count']]]);
        try {
            $result = $this->fleet->uploadPathAndDistribute(Storage::disk('local')->path($path), $file->filename, [$file->target_key], null, false);
            $item = collect($result['results'] ?? [])->firstWhere('key', $file->target_key);
            if (! is_array($item) || ($item['success'] ?? false) !== true || ! isset($item['video_index']) || (int) $item['video_index'] !== (int) $live['video_count'] + 1) {
                throw new RuntimeException('No se confirmÃ³ la identidad del archivo cargado en el equipo 35.');
            }
            app(Wl35MediaMapping::class)->remember([$item], [$file->target_key], $file->media_id, $file->filename);
            $mapping = Wl35DeviceMedia::where('device_id', $id)->where('video_index', (int) $item['video_index'])->where('media_id', $file->media_id)->firstOrFail();
            $file->update(['evidence' => ['mapping_id' => $mapping->id, 'upload_identity' => $mapping->upload_identity], 'video_index' => (int) $item['video_index'], 'state' => 'ready', 'confirmed_at' => now(), 'last_error' => null]);
        } catch (Throwable $e) {
            $file->update(['state' => 'needs_review', 'last_error' => 'Carga 35 ambigua: '.CampaignError::publicMessage($e)]);
        } finally {
            Storage::disk('local')->delete($path);
        }
    }

    private function installZ2(CampaignDeviceFile $file, ?callable $stillWanted = null): void
    {
        $device = $this->z2($file);
        if (in_array($file->filename, $device['reportedPlaylist'] ?? [], true)) {
            $file->update(['state' => 'ready', 'owned' => $this->alreadyOwned($file), 'confirmed_at' => now(), 'last_error' => null]);

            return;
        }
        $source = $this->sources->resolve($file->media);
        if (! $source) {
            throw new RuntimeException('El archivo original de la campaÃ±a no estÃ¡ disponible.');
        }
        if ($source['kind'] !== 'private_cloud') {
            $path = 'campaigns/'.Str::uuid().'.mp4';
            try {
                $this->materializer->materialize($file->media, $path);
                $upload = $this->cloud->postFile('/api/media/upload', Storage::disk('local')->path($path), $file->filename);
                if (($upload['result'] ?? -1) !== 0 || ! is_string($upload['filename'] ?? null)) {
                    throw new RuntimeException('No se pudo preparar el archivo en la nube 42.');
                }
                $file->update(['filename' => $upload['filename']]);
            } finally {
                Storage::disk('local')->delete($path);
            }
        }
        if ($stillWanted !== null && ! $stillWanted()) {
            return;
        }
        $file->update(['state' => 'installing', 'owned' => true, 'requested_at' => now(), 'evidence' => ['report_version' => $device['reportedPlaylistVersion'] ?? 0]]);
        $result = $this->cloud->post('/api/devices/'.substr($file->target_key, 3).'/play', ['filename' => $file->filename]);
        if (($result['result'] ?? -1) !== 0) {
            $file->update(['last_error' => 'La instalaciÃ³n 42 no fue aceptada; esperando evidencia antes de reintentar.']);
        }
    }

    private function alreadyOwned(CampaignDeviceFile $file, ?string $uploadIdentity = null): bool
    {
        if (CampaignDevicePlayback::where('target_key', $file->target_key)->first()?->preserves($file)) {
            return false;
        }
        $query = CampaignDeviceFile::where('target_key', $file->target_key)->where('owned', true)->whereIn('state', ['ready', 'installing']);

        return str_starts_with($file->target_key, 'z2:')
            ? $query->where('filename', $file->filename)->exists()
            : ($uploadIdentity !== null && $query->where('evidence->upload_identity', $uploadIdentity)->exists());
    }

    public function confirmInstallation(CampaignDeviceFile $file): void
    {
        if (! str_starts_with($file->target_key, 'z2:')) {
            $file->update(['state' => 'needs_review', 'last_error' => 'La carga 35 se interrumpiÃ³. Revisa la identidad antes de reintentar.']);

            return;
        }
        $device = $this->z2($file);
        if (($device['reportedPlaylistVersion'] ?? 0) > ($file->evidence['report_version'] ?? 0) && $device['reportedPlaylistAt'] > $file->requested_at->getTimestampMs() && in_array($file->filename, $device['reportedPlaylist'] ?? [], true)) {
            $file->update(['state' => 'ready', 'confirmed_at' => now(), 'last_error' => null]);

            return;
        }
        // Reassert the same desired file, never another file while this installation is pending.
        if ($file->requested_at->lt(now()->subMinutes(5))) {
            $this->cloud->post('/api/devices/'.substr($file->target_key, 3).'/play', ['filename' => $file->filename]);
            $file->update(['last_error' => 'Esperando que el equipo 42 reporte el archivo instalado.']);
        }
    }

    public function remove(CampaignDeviceFile $file): void
    {
        $playback = CampaignDevicePlayback::where('target_key', $file->target_key)->first();
        if (! $file->owned || $playback?->preserves($file)) {
            $file->owned = false;
            $file->update(['state' => 'preserved', 'last_error' => null]);

            return;
        }
        if (str_starts_with($file->target_key, 'z2:')) {
            $this->removeZ2($file);

            return;
        }
        if ($file->state === 'removing') {
            $file->update(['state' => 'needs_review', 'last_error' => 'El borrado 35 se interrumpiÃ³; no se repite un Ã­ndice ambiguo.']);

            return;
        }
        $live = $this->wl35($file);
        $id = substr($file->target_key, 5);
        $mapping = Wl35DeviceMedia::whereKey($file->evidence['mapping_id'] ?? 0)->where('device_id', $id)->where('media_id', $file->media_id)->first();
        if (! $mapping || ! ($file->evidence['upload_identity'] ?? null) || $mapping->upload_identity !== $file->evidence['upload_identity'] || $mapping->video_index > (int) $live['video_count']) {
            $file->update(['state' => 'needs_review', 'last_error' => 'No se puede identificar con seguridad el archivo 35.']);

            return;
        }
        $index = $mapping->video_index;
        $file->update(['state' => 'removing', 'requested_at' => now()]);
        try {
            $result = $this->fleet->deleteWl35Video($id, $index, (int) $live['video_count']);
            if (($result['confirmed'] ?? false) !== true || ($result['deletion_status'] ?? '') !== 'confirmed' || (int) ($result['video_count'] ?? -1) !== (int) $live['video_count'] - 1) {
                throw new RuntimeException('Respuesta de borrado sin evidencia del dispositivo.');
            }
            DB::transaction(function () use ($id, $index, $file): void {
                Wl35DeviceMedia::where('device_id', $id)->where('video_index', $index)->delete();
                Wl35DeviceMedia::where('device_id', $id)->where('video_index', '>', $index)->orderBy('video_index')->get()->each(fn ($m) => $m->decrement('video_index'));
                CampaignDeviceFile::where('target_key', $file->target_key)->where('video_index', '>', $index)->decrement('video_index');
                $file->update(['state' => 'removed', 'confirmed_at' => now(), 'last_error' => null]);
            });
        } catch (Throwable $e) {
            Wl35DeviceMedia::where('device_id', $id)->update(['media_id' => null, 'filename' => null]);
            $file->update(['state' => 'needs_review', 'last_error' => 'Borrado 35 ambiguo; no se reintentarÃ¡ el Ã­ndice: '.CampaignError::publicMessage($e)]);
        }
    }

    private function removeZ2(CampaignDeviceFile $file): void
    {
        $device = $this->z2($file);
        $requestId = $file->evidence['removal_request_id'] ?? null;
        $removal = collect($device['mediaRemovals'] ?? [])->first(fn ($r) => $requestId && ($r['requestId'] ?? null) === $requestId);
        if (($removal['status'] ?? null) === 'reported_absent') {
            $file->update(['state' => 'reported_absent', 'confirmed_at' => null, 'last_error' => 'Ausencia reportada en la lista del equipo 42; borrado físico de SD no verificable.']);

            return;
        }
        if (! $requestId) {
            $requestId = (string) Str::uuid();
            $file->update(['state' => 'removing', 'requested_at' => now(), 'evidence' => array_merge($file->evidence ?? [], ['removal_request_id' => $requestId])]);
        }
        $response = $this->cloud->post('/api/devices/'.substr($file->target_key, 3).'/remove-media', ['filename' => $file->filename, 'request_id' => $requestId]);
        if (($response['result'] ?? -1) !== 0) {
            throw new RuntimeException('No se pudo solicitar el retiro del archivo 42.');
        }
    }

    public function power(string $target, bool $value): void
    {
        $result = $this->fleet->sendCommand(['command' => 'power', 'targets' => [$target], 'value' => $value]);
        if ((collect($result['results'] ?? [])->firstWhere('key', $target)['success'] ?? false) !== true) {
            throw new RuntimeException('No se confirmó la detención o reanudación del dispositivo.');
        }
    }

    public function select(CampaignDeviceFile $file): void
    {
        if (str_starts_with($file->target_key, 'z2:')) {
            $result = $this->cloud->post('/api/devices/'.substr($file->target_key, 3).'/select-media', ['filename' => $file->filename]);
            if (($result['result'] ?? -1) !== 0) {
                throw new RuntimeException('El equipo 42 no aceptÃ³ seleccionar el archivo instalado.');
            }
        } else {
            $this->wl35($file);
            $mapping = Wl35DeviceMedia::whereKey($file->evidence['mapping_id'] ?? 0)->where('device_id', substr($file->target_key, 5))->where('media_id', $file->media_id)->first();
            if (! $mapping || $mapping->upload_identity !== ($file->evidence['upload_identity'] ?? null)) {
                throw new RuntimeException('El Ã­ndice 35 requiere revisiÃ³n antes de reproducir.');
            }
            $result = $this->fleet->sendCommand(['command' => 'play', 'targets' => [$file->target_key], 'wl35_video_index' => $mapping->video_index]);
            if (collect($result['results'] ?? [])->firstWhere('key', $file->target_key)['success'] ?? false) {
                return;
            }
            throw new RuntimeException('El equipo 35 no confirmÃ³ la selecciÃ³n.');
        }
    }
}
