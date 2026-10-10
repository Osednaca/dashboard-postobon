<?php

namespace App\Services\Fleet;

use App\Models\Media;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class MediaSourceMaterializer
{
    private const MAX_BYTES = 250 * 1024 * 1024;

    public function __construct(private readonly MediaPreviewSource $sources) {}

    public function materialize(Media $media, string $destination): int
    {
        $localDisk = Storage::disk('local');
        $localDisk->makeDirectory(dirname($destination));
        $destinationPath = $localDisk->path($destination);

        $resolved = str_starts_with($media->mime_type, 'video/') ? $this->sources->resolve($media) : null;
        if ($resolved === null) {
            throw new RuntimeException('No se pudo encontrar el archivo de video seleccionado.');
        }
        if ($resolved['kind'] === 'local') {
            $source = fopen($resolved['path'], 'rb');

            if ($source === false) {
                throw new RuntimeException('No fue posible abrir el video almacenado localmente.');
            }

            try {
                if (! $localDisk->writeStream($destination, $source)) {
                    throw new RuntimeException('No fue posible preparar el video local para su distribución.');
                }
            } finally {
                fclose($source);
            }
        } else {
            $this->download($resolved['path'], $destinationPath);
        }

        $size = filesize($destinationPath);
        if ($size === false || $size < 1) {
            $localDisk->delete($destination);
            throw new RuntimeException('La biblioteca devolvió un archivo de video vacío.');
        }

        if ($size > self::MAX_BYTES) {
            $localDisk->delete($destination);
            throw new RuntimeException('El video supera el límite de 250 MB para distribución.');
        }

        return $size;
    }

    private function download(string $sourcePath, string $destinationPath): void
    {
        $privateCloudBase = rtrim((string) config('privatecloud.base_url'), '/');
        $url = $privateCloudBase.$sourcePath;
        $request = Http::connectTimeout((int) config('privatecloud.connect_timeout', 10))
            ->timeout((int) config('unifiedfleet.upload_timeout', 900))
            ->withOptions(['sink' => $destinationPath, 'allow_redirects' => false]);

        if (
            filled(config('privatecloud.token'))
            && $privateCloudBase !== ''
            && str_starts_with($url, $privateCloudBase.'/')
        ) {
            $request = $request->withToken((string) config('privatecloud.token'));
        }

        try {
            $response = $request->get($url);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'No fue posible descargar el video desde la biblioteca privada.',
                previous: $exception,
            );
        }

        if (! $response->successful()) {
            throw new RuntimeException(
                "La biblioteca no permitió descargar el video (HTTP {$response->status()})."
            );
        }
    }
}
