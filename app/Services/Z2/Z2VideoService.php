<?php

namespace App\Services\Z2;

use App\Models\Media;
use App\Services\PrivateCloud\PrivateCloudClient;
use Illuminate\Support\Facades\Log;

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
     * @return \Illuminate\Database\Eloquent\Collection<int, Media>
     */
    public function syncVideos()
    {
        $response = $this->client->get('/api/media');

        if ($response === null) {
            Log::error('[PrivateCloud] Video sync aborted: API unavailable');

            return collect();
        }

        $videosToUpsert = [];
        $filenames = [];

        foreach (($response['media'] ?? []) as $item) {
            $filename = (string) ($item['filename'] ?? '');
            if ($filename === '') {
                continue;
            }

            $filenames[] = $filename;
            $videosToUpsert[] = [
                'file_path' => $filename,
                'name' => pathinfo($filename, PATHINFO_FILENAME),
                'original_name' => $filename,
                'mime_type' => 'video/mp4',
                'size' => (int) ($item['size'] ?? 0),
                'duration' => 0,
            ];
        }

        if (! empty($videosToUpsert)) {
            Media::upsert(
                $videosToUpsert,
                ['file_path'],
                ['name', 'original_name', 'mime_type', 'size', 'duration']
            );
        }

        // Eliminar videos que ya no están en la nube privada (solo los que
        // tienen file_path "de nube", es decir, sin ruta local).
        Media::whereNotIn('file_path', $filenames)
            ->where('file_path', 'NOT LIKE', '%/%')
            ->delete();

        $synced = Media::whereIn('file_path', $filenames)->get();

        Log::info('[PrivateCloud] Synced '.count($synced).' videos');

        return $synced;
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

        $media = Media::updateOrCreate(
            ['file_path' => $filename],
            [
                'name' => pathinfo($filename, PATHINFO_FILENAME),
                'original_name' => $filename,
                'mime_type' => 'video/mp4',
                'size' => (int) ($response['size'] ?? 0),
                'duration' => (int) $duration,
            ]
        );

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
