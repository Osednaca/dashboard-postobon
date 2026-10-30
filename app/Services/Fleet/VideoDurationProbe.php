<?php

namespace App\Services\Fleet;

use App\Models\Media;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Throwable;

class VideoDurationProbe
{
    public function __construct(private readonly MediaSourceMaterializer $materializer) {}

    /** Return a whole number of seconds without guessing if probing fails. */
    public function file(string $path): ?int
    {
        try {
            $result = Process::timeout(20)->run([
                config('mediapreview.ffprobe'), '-v', 'error', '-protocol_whitelist', 'file,pipe',
                '-enable_drefs', '0', '-use_absolute_path', '0', '-i', $path,
                '-select_streams', 'v:0', '-show_entries', 'stream=duration:format=duration', '-of', 'json',
            ]);
            $data = json_decode($result->output(), true);
            $duration = $data['streams'][0]['duration'] ?? null;
            if (! is_numeric($duration) || (float) $duration <= 0) {
                $duration = $data['format']['duration'] ?? null;
            }
            if (! $result->successful() || ! is_numeric($duration) || ! is_finite((float) $duration)
                || (float) $duration <= 0 || (float) $duration > 2147483647) {
                return null;
            }

            return (int) ceil((float) $duration);
        } catch (Throwable) {
            return null;
        }
    }

    /** Fetch only a validated source, and never keep temporary media after probing. */
    public function recover(Media $media): ?int
    {
        $path = 'media-duration/'.bin2hex(random_bytes(16)).'.input';
        $disk = Storage::disk('local');
        try {
            $this->materializer->materialize($media, $path, (int) config('mediapreview.download_timeout'), (int) config('mediapreview.max_source_bytes'));

            return $this->file($disk->path($path));
        } catch (Throwable) {
            return null;
        } finally {
            $disk->delete($path);
        }
    }
}
