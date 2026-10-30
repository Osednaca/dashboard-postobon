<?php

namespace App\Services\Campaigns;

use App\Models\CampaignBaselineFile;
use App\Models\CampaignDeviceFile;
use App\Models\CampaignDevicePlayback;
use App\Models\Media;
use App\Models\Wl35DeviceMedia;
use App\Services\Fleet\MediaSourceMaterializer;
use App\Services\Z2\Z2VideoService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/** Call under the device lock; the configured disk must be backed by a persistent volume. */
class CampaignBaselineBackup
{
    public function __construct(private MediaSourceMaterializer $materializer, private Z2VideoService $videos) {}

    public function files(CampaignDevicePlayback $playback): Collection
    {
        $session = $playback->baseline['session_id'] ?? null;
        if (! is_string($session) || ! Str::isUuid($session)) {
            throw new RuntimeException('No existe una sesión válida para respaldar los videos anteriores.');
        }

        return CampaignBaselineFile::where('playback_id', $playback->id)->where('session_id', $session)->orderBy('position')->get();
    }

    /** Create at most one backup per call; true means all files were read back and verified. */
    public function prepare(CampaignDevicePlayback $playback): bool
    {
        $baseline = $playback->baseline;
        $saved = $this->files($playback);
        if (! is_array($baseline) || ! in_array($baseline['type'] ?? null, ['z2', 'wl35'], true)) {
            throw new RuntimeException('No se conoce el contenido anterior; no se puede retirarlo sin respaldo.');
        }
        if ($baseline['type'] === 'z2' && ! is_array($baseline['files'] ?? null)) {
            throw new RuntimeException('El inventario anterior no es válido para preparar el respaldo.');
        }
        $count = $baseline['type'] === 'z2' ? count($baseline['files']) : ($baseline['count'] ?? null);
        if (! is_int($count) || $count < 0 || ($baseline['type'] === 'wl35' && $count > 255)) {
            throw new RuntimeException('El inventario anterior no es válido para preparar el respaldo.');
        }
        if ($count === 0) {
            if ($saved->isNotEmpty()) {
                throw new RuntimeException('El inventario de respaldo no coincide con el contenido anterior.');
            }

            return true;
        }
        $diskName = config('campaigns.backup_disk');
        if (! is_string($diskName) || $diskName === '' || config('campaigns.backup_persistent') !== true || config('filesystems.disks.'.$diskName.'.driver') !== 'local') {
            throw new RuntimeException('Configura un disco local persistente para respaldar el contenido anterior antes de iniciar la campaña.');
        }
        if ($saved->count() === $count) {
            foreach ($saved as $offset => $file) {
                if ($file->position !== $offset + 1 || ($baseline['type'] === 'z2' && $file->filename !== $baseline['files'][$offset]) || ($baseline['type'] === 'wl35' && $file->original_index !== $offset + 1)) {
                    throw new RuntimeException('El inventario de respaldo no coincide con el contenido anterior.');
                }
                $this->verify($file);
            }

            return true;
        }
        if ($saved->count() > $count) {
            throw new RuntimeException('El inventario de respaldo no coincide con el contenido anterior.');
        }

        // Resolve every original before copying any; an unidentified later index blocks all removals.
        $items = $this->resolveItems($playback);
        foreach ($saved as $file) {
            $expected = $items[$file->position - 1] ?? null;
            if (! $expected || $file->media_id !== $expected['media']->id || $file->filename !== $expected['filename'] || $file->original_index !== $expected['original_index'] || $file->upload_identity !== $expected['upload_identity']) {
                throw new RuntimeException('El inventario anterior cambió durante la preparación del respaldo.');
            }
            $this->verify($file);
        }
        foreach ($items as $offset => $item) {
            if ($saved->firstWhere('position', $offset + 1)) {
                continue;
            }
            $this->copy($playback, $diskName, $offset + 1, $item);

            return false;
        }

        throw new RuntimeException('El inventario anterior no pudo respaldarse completamente.');
    }

    public function verify(CampaignBaselineFile $file): string
    {
        if (config('filesystems.disks.'.$file->backup_disk.'.driver') !== 'local') {
            throw new RuntimeException('El disco de respaldo anterior no está disponible localmente.');
        }
        $disk = Storage::disk($file->backup_disk);
        $path = $disk->path($file->backup_path);
        if (! is_file($path) || filesize($path) !== $file->backup_size || ! hash_equals($file->backup_hash, (string) hash_file('sha256', $path))) {
            throw new RuntimeException('La copia de seguridad del video anterior falta o está dañada.');
        }

        return $path;
    }

    private function resolveItems(CampaignDevicePlayback $playback): array
    {
        $baseline = $playback->baseline;
        if ($baseline['type'] === 'wl35') {
            $mappings = Wl35DeviceMedia::where('device_id', substr($playback->target_key, 5))->with('media')->get()->groupBy('video_index');
            $items = [];
            for ($index = 1; $index <= $baseline['count']; $index++) {
                $matches = $mappings->get($index, collect());
                $mapping = $matches->count() === 1 ? $matches->first() : null;
                if (! $mapping?->media || ! $mapping->upload_identity) {
                    throw new RuntimeException("No se puede identificar y respaldar el video anterior del índice {$index} del equipo 35.");
                }
                $items[] = ['media' => $mapping->media, 'filename' => $mapping->filename, 'original_index' => $index, 'upload_identity' => $mapping->upload_identity];
            }

            return $items;
        }
        $names = $baseline['files'] ?? null;
        if (! is_array($names) || count($names) !== count(array_unique($names)) || collect($names)->contains(fn ($name) => ! is_string($name) || $name === '')) {
            throw new RuntimeException('La lista anterior del equipo 42 no permite identificar sus videos con seguridad.');
        }
        $catalog = Media::where('mime_type', 'like', 'video/%')->get();
        $items = [];
        foreach ($names as $name) {
            $ids = CampaignDeviceFile::where('target_key', $playback->target_key)->where('filename', $name)->whereNotNull('media_id')->pluck('media_id');
            $matches = $catalog->filter(fn ($media) => $ids->contains($media->id) || $this->videos->cloudFilename($media->file_path) === $name)->unique('id');
            if ($matches->count() !== 1) {
                throw new RuntimeException("No se puede identificar y respaldar el video anterior {$name} del equipo 42.");
            }
            $items[] = ['media' => $matches->first(), 'filename' => $name, 'original_index' => null, 'upload_identity' => null];
        }

        return $items;
    }

    private function copy(CampaignDevicePlayback $playback, string $diskName, int $position, array $item): void
    {
        $temporary = 'campaign-backup-temp/'.Str::uuid();
        $destination = 'campaign-baseline/'.$playback->id.'/'.$playback->baseline['session_id'].'/'.Str::uuid();
        $local = Storage::disk('local');
        $disk = Storage::disk($diskName);
        try {
            $size = $this->materializer->materialize($item['media'], $temporary);
            $sourcePath = $local->path($temporary);
            $hash = hash_file('sha256', $sourcePath);
            $stream = fopen($sourcePath, 'rb');
            if (! is_string($hash) || $stream === false) {
                throw new RuntimeException('No fue posible leer el video para respaldarlo.');
            }
            try {
                if (! $disk->writeStream($destination, $stream)) {
                    throw new RuntimeException('No fue posible guardar el respaldo del video anterior.');
                }
            } finally {
                fclose($stream);
            }
            $file = new CampaignBaselineFile(['playback_id' => $playback->id, 'session_id' => $playback->baseline['session_id'], 'position' => $position, 'media_id' => $item['media']->id, 'filename' => $item['filename'], 'original_index' => $item['original_index'], 'upload_identity' => $item['upload_identity'], 'backup_disk' => $diskName, 'backup_path' => $destination, 'backup_hash' => $hash, 'backup_size' => $size, 'state' => 'backed_up']);
            $this->verify($file);
            $file->save();
        } catch (Throwable $exception) {
            $disk->delete($destination);
            throw $exception;
        } finally {
            $local->delete($temporary);
        }
    }
}
