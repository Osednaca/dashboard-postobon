<?php

namespace App\Jobs;

use App\Models\FleetUpload;
use App\Models\Wl35DeviceMedia;
use App\Services\Fleet\MediaSourceMaterializer;
use App\Services\Fleet\UnifiedFleetClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PlayFleetMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1900;

    public bool $failOnTimeout = true;

    public function __construct(public readonly string $fleetUploadId) {}

    public function handle(
        UnifiedFleetClient $fleetClient,
        MediaSourceMaterializer $materializer,
    ): void {
        $upload = FleetUpload::with('sourceMedia')->findOrFail($this->fleetUploadId);

        if ($upload->status === 'completed') {
            return;
        }

        $upload->update([
            'status' => 'processing',
            'phase' => 'preparing_media',
            'progress' => 15,
            'started_at' => now(),
            'error' => null,
        ]);

        try {
            $media = $upload->sourceMedia;
            if ($media === null) {
                throw new \RuntimeException('El medio fue eliminado antes de iniciar la reproducción.');
            }

            $targets = array_values(array_unique($upload->targets ?? []));
            $z2Targets = array_values(array_filter(
                $targets,
                fn (string $target): bool => str_starts_with($target, 'z2:'),
            ));
            $wl35Targets = array_values(array_filter(
                $targets,
                fn (string $target): bool => str_starts_with($target, 'wl35:'),
            ));
            $isCloudLibraryFile = ! str_contains($media->file_path, '/')
                && ! str_contains($media->file_path, '\\')
                && ! str_starts_with($media->file_path, 'http://')
                && ! str_starts_with($media->file_path, 'https://');
            $results = [];
            $wl35UploadTargets = $wl35Targets;

            if ($wl35Targets !== []) {
                [$knownResults, $wl35UploadTargets] = $this->playKnownWl35Media(
                    $fleetClient,
                    $wl35Targets,
                    $media->id,
                );
                $results = array_merge($results, $knownResults);
            }

            if ($isCloudLibraryFile && $z2Targets !== []) {
                $upload->update(['phase' => 'sending_z2', 'progress' => 22]);
                $results = array_merge(
                    $results,
                    $this->runZ2Playback($fleetClient, $z2Targets, $media->file_path),
                );
            }

            $distributionTargets = $isCloudLibraryFile
                ? $wl35UploadTargets
                : array_values(array_unique(array_merge($z2Targets, $wl35UploadTargets)));

            if ($distributionTargets !== []) {
                $upload->update(['phase' => 'downloading_media', 'progress' => 30]);

                try {
                    $materializer->materialize($media, $upload->file_path);
                    $upload->update(['phase' => 'uploading_gateway', 'progress' => 40]);

                    $distribution = $fleetClient->uploadPathAndDistribute(
                        Storage::disk('local')->path($upload->file_path),
                        $upload->original_name,
                        $distributionTargets,
                        function (string $phase, int $progress) use ($upload): void {
                            $mappedProgress = match ($phase) {
                                'uploading_gateway' => 45,
                                'distributing' => 65,
                                'finalizing' => 92,
                                default => max(40, min(92, $progress)),
                            };
                            $upload->update(['phase' => $phase, 'progress' => $mappedProgress]);
                        },
                        true,
                    );
                    $distributionResults = $distribution['results'] ?? [];
                    $this->rememberWl35Indexes($distributionResults, $media->id);
                    $results = array_merge($results, $distributionResults);
                } catch (Throwable $exception) {
                    Log::error('Falló la rama de carga para reproducción instantánea.', [
                        'fleet_upload_id' => $upload->id,
                        'targets' => $distributionTargets,
                        'error' => $exception->getMessage(),
                    ]);
                    $results = array_merge(
                        $results,
                        $this->failedResults($distributionTargets, $exception->getMessage()),
                    );
                }
            }

            $upload->update(['phase' => 'finalizing', 'progress' => 95]);
            $summary = $this->summarize($targets, $results);
            $upload->update([
                'status' => 'completed',
                'phase' => 'completed',
                'progress' => 100,
                'result' => $summary,
                'completed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $this->markAsFailed($exception->getMessage());
            throw $exception;
        } finally {
            Storage::disk('local')->delete($upload->file_path);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->markAsFailed($exception?->getMessage() ?? 'La reproducción instantánea se interrumpió.');

        $upload = FleetUpload::find($this->fleetUploadId);
        if ($upload !== null) {
            Storage::disk('local')->delete($upload->file_path);
        }
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

    /** @param array<int, array<string, mixed>> $results */
    private function rememberWl35Indexes(array $results, int $mediaId): void
    {
        collect($results)
            ->filter(fn ($result): bool => is_array($result)
                && (($result['success'] ?? false) === true || ($result['uploaded'] ?? false) === true)
                && ($result['type'] ?? null) === 'wl35'
                && filter_var($result['video_index'] ?? null, FILTER_VALIDATE_INT) !== false
                && (int) $result['video_index'] > 0)
            ->each(function (array $result) use ($mediaId): void {
                $deviceId = (string) ($result['id'] ?? '');
                $videoIndex = (int) $result['video_index'];

                try {
                    Wl35DeviceMedia::where('device_id', $deviceId)
                        ->where('video_index', $videoIndex)
                        ->where('media_id', '!=', $mediaId)
                        ->delete();
                    Wl35DeviceMedia::updateOrCreate(
                        ['device_id' => $deviceId, 'media_id' => $mediaId],
                        ['video_index' => $videoIndex],
                    );
                } catch (Throwable $exception) {
                    Log::error('El video se cargó, pero no fue posible guardar su índice WL35.', [
                        'device_id' => $deviceId,
                        'media_id' => $mediaId,
                        'video_index' => $videoIndex,
                        'error' => $exception->getMessage(),
                    ]);
                }
            });
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
            'success' => $succeeded === count($ordered),
            'total' => count($ordered),
            'succeeded' => $succeeded,
            'failed' => count($ordered) - $succeeded,
            'results' => $ordered,
        ];
    }

    private function markAsFailed(string $message): void
    {
        $updated = FleetUpload::whereKey($this->fleetUploadId)
            ->whereNotIn('status', ['completed', 'failed'])
            ->update([
                'status' => 'failed',
                'phase' => 'failed',
                'error' => $message,
                'completed_at' => now(),
            ]);

        if ($updated > 0) {
            Log::error('Falló la reproducción de un medio de la flota.', [
                'fleet_upload_id' => $this->fleetUploadId,
                'error' => $message,
            ]);
        }
    }
}
