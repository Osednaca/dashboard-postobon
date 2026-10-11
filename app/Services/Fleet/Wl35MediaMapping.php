<?php

namespace App\Services\Fleet;

use App\Models\Wl35DeviceMedia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class Wl35MediaMapping
{
    /** @param array<int, array<string, mixed>> $results */
    public function remember(array $results, array $targets, ?int $mediaId, string $filename): void
    {
        $filename = basename(str_replace('\\', '/', $filename));
        if ($filename === '' || preg_match('/[\x00-\x1f]/', $filename)) {
            return;
        }

        foreach ($results as $result) {
            if (! is_array($result) || ($result['type'] ?? null) !== 'wl35'
                || (($result['success'] ?? false) !== true && ($result['uploaded'] ?? false) !== true)
                || filter_var($result['video_index'] ?? null, FILTER_VALIDATE_INT) === false
                || (int) $result['video_index'] < 1 || (int) $result['video_index'] > 255) {
                continue;
            }
            $deviceId = (string) ($result['id'] ?? '');
            if ($deviceId === '' || ($result['key'] ?? null) !== 'wl35:'.$deviceId
                || ! in_array('wl35:'.$deviceId, $targets, true)) {
                continue;
            }
            $index = (int) $result['video_index'];

            try {
                DB::transaction(function () use ($deviceId, $index, $mediaId, $filename): void {
                    if ($mediaId !== null) {
                        Wl35DeviceMedia::with('media')->where('device_id', $deviceId)
                            ->where('media_id', $mediaId)->where('video_index', '!=', $index)
                            ->get()->each(fn (Wl35DeviceMedia $mapping) => $mapping->update([
                                'filename' => $mapping->display_filename,
                                'media_id' => null,
                            ]));
                    }
                    Wl35DeviceMedia::updateOrCreate(
                        ['device_id' => $deviceId, 'video_index' => $index],
                        ['media_id' => $mediaId, 'filename' => $filename],
                    );
                });
            } catch (Throwable $exception) {
                Log::error('El archivo se cargó, pero no fue posible guardar su nombre WL35.', [
                    'device_id' => $deviceId, 'video_index' => $index,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }
}
