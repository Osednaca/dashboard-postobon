<?php

namespace App\Services\Z2;

use App\Models\Media;
use App\Services\PrivateCloud\PrivateCloudClient;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Adaptador de videos sobre la nube privada (fan-private-cloud).
 *
 * En la nube privada el video se identifica por su nombre de archivo (no por
 * "uiCode" numérico como en la nube china). Aquí file_path guarda el filename
 * real en el almacenamiento de la nube privada.
 */
class Z2VideoService
{
    private PrivateCloudClient $client;

    public function __construct(PrivateCloudClient $client)
    {
        $this->client = $client;
    }

    /**
     * Sincroniza la biblioteca de videos de la nube privada a la base local.
     *
     * @return Collection<int, Media>
     */
    public function syncVideos()
    {
        $response = $this->client->get('/api/media');

        if (! $this->validInventory($response)) {
            Log::warning('[PrivateCloud] Video sync aborted: unavailable or incomplete inventory');

            return collect();
        }

        $filenames = array_column($response['media'], 'filename');
        DB::transaction(function () use ($response, $filenames): void {
            $known = Media::withTrashed()->get();
            $byPath = $known->keyBy('file_path');
            $identified = $known->mapWithKeys(fn (Media $media) => [$media->id => $this->cloudFilename($media->file_path)]);
            $byFilename = $known->groupBy(fn (Media $media) => $identified->get($media->id) ?? '');
            $present = array_fill_keys($filenames, true);
            foreach ($response['media'] as $item) {
                $filename = $item['filename'];
                $local = $byPath->get($filename);
                if ($local && $identified->get($local->id) === null) {
                    continue;
                }
                $matches = $byFilename->get($filename, collect());
                $media = $matches->firstWhere('file_path', $filename) ?? $matches->first();
                if ($media) {
                    // Background inventory reconciliation never resurrects a tombstone.
                    if (! $media->trashed()) {
                        $media->update(['file_path' => $filename, 'size' => $item['size']]);
                    }
                    $matches->where('id', '!=', $media->id)->filter(fn (Media $duplicate) => ! $duplicate->trashed())
                        ->each(fn (Media $duplicate) => $duplicate->delete());

                    continue;
                }
                Media::create([
                    'file_path' => $filename, 'name' => pathinfo($filename, PATHINFO_FILENAME),
                    'original_name' => $filename, 'mime_type' => 'video/mp4', 'size' => $item['size'],
                ]);
            }
            foreach ($known as $media) {
                $filename = $identified->get($media->id);
                if (! $media->trashed() && $filename !== null && ! isset($present[$filename])) {
                    $media->delete();
                }
            }
        });
        $synced = Media::whereIn('file_path', $filenames)->get();

        Log::info('[PrivateCloud] Synced '.count($synced).' videos');

        return $synced;
    }

    private function validInventory(?array $response): bool
    {
        if (($response['result'] ?? null) !== 0 || ! isset($response['media'])
            || ! is_array($response['media']) || ! array_is_list($response['media'])) {
            return false;
        }
        $seen = [];
        foreach ($response['media'] as $item) {
            if (! is_array($item) || ! isset($item['filename'], $item['size'])
                || ! is_string($item['filename']) || ! $this->safeFilename($item['filename'])
                || ! is_int($item['size']) || $item['size'] < 0 || isset($seen[$item['filename']])) {
                return false;
            }
            $seen[$item['filename']] = true;
        }

        return true;
    }

    /** Identify only this private library; independent local/external paths are preserved. */
    public function cloudFilename(string $path): ?string
    {
        if ($this->safeFilename($path)) {
            return Storage::disk('public')->exists($path) ? null : $path;
        }
        $base = rtrim((string) config('privatecloud.base_url'), '/');
        $prefix = $base.'/fileDownload/Videos/';
        if ($base !== '' && str_starts_with($path, $prefix)) {
            $suffix = substr($path, strlen($prefix));
            $filename = rawurldecode($suffix);
            if (! str_contains($suffix, '?') && ! str_contains($suffix, '#') && $this->safeFilename($filename)) {
                return $filename;
            }
        }

        return null;
    }

    private function safeFilename(string $filename): bool
    {
        return $filename !== '' && ! in_array($filename, ['.', '..'], true)
            && ! preg_match('/[\\\\\/:\x00-\x1f]/', $filename);
    }

    /**
     * Sube un video a la biblioteca de la nube privada.
     */
    public function uploadVideo(string $filePath, string $fileName, int $duration = 0): ?Media
    {
        // Asegurar extensión correcta (la nube privada solo acepta video)
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        if ($extension && ! str_ends_with(strtolower($fileName), '.'.strtolower($extension))) {
            $fileName .= '.'.$extension;
        }

        $response = $this->client->postFile('/api/media/upload', $filePath, $fileName);

        if ($response === null || ($response['result'] ?? -1) !== 0) {
            Log::error('[PrivateCloud] Upload failed', ['fileName' => $fileName, 'response' => $response]);

            return null;
        }

        $filename = (string) ($response['filename'] ?? '');
        if (! $this->safeFilename($filename)) {
            return null;
        }

        $media = Media::withTrashed()->updateOrCreate(
            ['file_path' => $filename],
            [
                'name' => pathinfo($filename, PATHINFO_FILENAME),
                'original_name' => $filename,
                'mime_type' => 'video/mp4',
                'size' => (int) ($response['size'] ?? 0),
                'duration' => $duration > 0 ? $duration : null,
            ]
        );
        if ($media->trashed()) {
            $media->restore();
        }

        Log::info('[PrivateCloud] Video uploaded', ['filename' => $filename]);

        return $media;
    }

    /**
     * Elimina un video de la nube privada.
     */
    public function deleteVideo(string $filename): bool
    {
        if ($filename === '') {
            return true;
        }

        $response = $this->client->delete('/api/media/'.rawurlencode(basename($filename)));

        return $response !== null && ($response['result'] ?? -1) === 0;
    }

    /**
     * En la nube privada el "identificador" del video ES su filename. Se
     * devuelve el filename resuelto desde la biblioteca local (o el propio
     * nombre si no está en la base).
     */
    public function getUiCodeByFileName(string $fileName): ?string
    {
        $media = Media::whereRaw('LOWER(name) = LOWER(?)', [$fileName])
            ->orWhereRaw('LOWER(original_name) = LOWER(?)', [$fileName])
            ->orWhereRaw('LOWER(file_path) = LOWER(?)', [$fileName])
            ->first();

        if ($media) {
            return $media->file_path;
        }

        // Si el archivo no está indexado, devolver solo el basename; el
        // dispositivo y la nube privada identifican por filename.
        return basename($fileName);
    }

    /**
     * URL de descarga del video en la nube privada.
     */
    public function getVideoUrl(string $filename, string $displayName = ''): string
    {
        return $this->client->baseUrl().'/fileDownload/Videos/'.rawurlencode($filename);
    }
}
