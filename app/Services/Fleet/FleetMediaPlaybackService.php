<?php

namespace App\Services\Fleet;

use App\Models\Media;
use App\Models\Wl35DeviceMedia;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class FleetMediaPlaybackService
{
    public function __construct(
        private readonly UnifiedFleetClient $fleetClient,
        private readonly MediaSourceMaterializer $materializer,
        private readonly MediaPreviewSource $sources,
    ) {}

    /**
     * Play a library selection without creating a user-owned upload operation.
     * The caller owns the temporary file lifecycle.
     *
     * @param  array<int, string>  $targets
     * @return array<string, mixed>
     */
    public function play(
        Media $media,
        array $targets,
        string $originalName,
        string $temporaryPath,
        ?callable $progress = null,
    ): array {
        $report = $progress ?? static function (string $phase, int $progress): void {};
        $targets = array_values(array_unique($targets));
        $z2Targets = array_values(array_filter(
            $targets,
            fn (string $target): bool => str_starts_with($target, 'z2:'),
        ));
        $wl35Targets = array_values(array_filter(
            $targets,
            fn (string $target): bool => str_starts_with($target, 'wl35:'),
        ));
        $source = str_starts_with($media->mime_type, 'video/') ? $this->sources->resolve($media) : null;
        if ($source === null) {
            throw new \RuntimeException('No se pudo encontrar el archivo de video seleccionado.');
        }
        $isCloudLibraryFile = $source['kind'] === 'private_cloud';
        $results = [];
        $wl35UploadTargets = $wl35Targets;

        if ($wl35Targets !== []) {
            [$knownResults, $wl35UploadTargets] = $this->playKnownWl35Media(
                $this->fleetClient,
                $wl35Targets,
                $media->id,
            );
            $results = array_merge($results, $knownResults);
        }

        if ($isCloudLibraryFile && $z2Targets !== []) {
            $report('sending_z2', 22);
            $results = array_merge(
                $results,
                $this->runZ2Playback($this->fleetClient, $z2Targets, rawurldecode(basename($source['path']))),
            );
        }

        $distributionTargets = $isCloudLibraryFile
            ? $wl35UploadTargets
            : array_values(array_unique(array_merge($z2Targets, $wl35UploadTargets)));

        if ($distributionTargets !== []) {
            $report('downloading_media', 30);

            try {
                $this->materializer->materialize($media, $temporaryPath);
                $report('uploading_gateway', 40);

                $distribution = $this->fleetClient->uploadPathAndDistribute(
                    Storage::disk('local')->path($temporaryPath),
                    $originalName,
                    $distributionTargets,
                    function (string $phase, int $progress) use ($report): void {
                        $mappedProgress = match ($phase) {
                            'uploading_gateway' => 45,
                            'distributing' => 65,
                            'finalizing' => 92,
                            default => max(40, min(92, $progress)),
                        };
                        $report($phase, $mappedProgress);
                    },
                    true,
                );
                $distributionResults = $distribution['results'] ?? [];
                app(Wl35MediaMapping::class)->remember($distributionResults, $distributionTargets, $media->id, $originalName);
                $results = array_merge($results, $distributionResults);
            } catch (Throwable $exception) {
                Log::error('Falló la rama de carga para reproducción instantánea.', [
                    'media_id' => $media->id,
                    'targets' => $distributionTargets,
                    'error' => $exception->getMessage(),
                ]);
                $results = array_merge(
                    $results,
                    $this->failedResults($distributionTargets, $exception->getMessage()),
                );
            }
        }

        $report('finalizing', 95);

        return $this->summarize($targets, $results);
    }

    /** @return array{0: array<int, array<string, mixed>>, 1: array<int, string>} */
    private function playKnownWl35Media(
        UnifiedFleetClient $fleetClient,
        array $targets,
        int $mediaId,
    ): array {
        $targetIds = collect($targets)->mapWithKeys(fn (string $key): array => [
            substr($key, strlen('wl35:')) => $key,
        ]);
        $remaining = collect($targets)->flip();
        $results = [];

        try {
            $liveById = collect($fleetClient->getFleet()['devices'] ?? [])
                ->where('type', 'wl35')
                ->keyBy(fn (array $device): string => (string) ($device['id'] ?? ''));
            $mappings = Wl35DeviceMedia::where('media_id', $mediaId)
                ->whereIn('device_id', $targetIds->keys())
                ->get();
            $targetsByIndex = [];

            foreach ($mappings as $mapping) {
                $live = $liveById->get($mapping->device_id);
                $reportedCount = (int) ($live['video_count'] ?? 0);

                if ($mapping->video_index > $reportedCount) {
                    $mapping->delete();

                    continue;
                }

                if (! ($live['online'] ?? false) || ! ($live['connected'] ?? false) || ! ($live['session_ready'] ?? false)) {
                    continue;
                }

                $key = $targetIds->get($mapping->device_id);
                $targetsByIndex[$mapping->video_index][] = $key;
                $remaining->forget($key);
            }

            foreach ($targetsByIndex as $index => $knownTargets) {
                try {
                    $response = $fleetClient->sendCommand([
                        'command' => 'play',
                        'targets' => array_values($knownTargets),
                        'wl35_video_index' => (int) $index,
                    ]);
                    $results = array_merge($results, $response['results'] ?? []);
                } catch (Throwable $exception) {
                    $results = array_merge($results, $this->failedResults($knownTargets, $exception->getMessage()));
                }
            }
        } catch (Throwable $exception) {
            Log::warning('No fue posible consultar los índices WL35 conocidos; se volverá a cargar el medio.', [
                'media_id' => $mediaId,
                'error' => $exception->getMessage(),
            ]);
        }

        return [$results, $remaining->keys()->values()->all()];
    }

    /** @return array<int, array<string, mixed>> */
    private function runZ2Playback(
        UnifiedFleetClient $fleetClient,
        array $targets,
        string $filename,
    ): array {
        try {
            $response = $fleetClient->sendCommand([
                'command' => 'play',
                'targets' => $targets,
                'z2_filename' => $filename,
            ]);

            return $response['results'] ?? [];
        } catch (Throwable $exception) {
            return $this->failedResults($targets, $exception->getMessage());
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function failedResults(array $targets, string $error): array
    {
        return array_map(fn (string $key): array => [
            'key' => $key,
            'type' => str_starts_with($key, 'wl35:') ? 'wl35' : 'z2',
            'id' => str_contains($key, ':') ? substr($key, strpos($key, ':') + 1) : $key,
            'success' => false,
            'error' => $error,
        ], $targets);
    }

    /** @return array<string, mixed> */
    private function summarize(array $targets, array $results): array
    {
        $byKey = collect($results)
            ->filter(fn ($result): bool => is_array($result) && isset($result['key']))
            ->keyBy('key');
        $ordered = collect($targets)->map(function (string $key) use ($byKey): array {
            return $byKey->get($key) ?? [
                'key' => $key,
                'type' => str_starts_with($key, 'wl35:') ? 'wl35' : 'z2',
                'id' => str_contains($key, ':') ? substr($key, strpos($key, ':') + 1) : $key,
                'success' => false,
                'error' => 'El gateway no devolvió resultado para este dispositivo.',
            ];
        })->values()->all();
        $succeeded = count(array_filter($ordered, fn (array $result): bool => ($result['success'] ?? false) === true));

        return [
            'success' => $ordered !== [] && $succeeded === count($ordered),
            'total' => count($ordered),
            'succeeded' => $succeeded,
            'failed' => count($ordered) - $succeeded,
            'results' => $ordered,
        ];
    }
}
