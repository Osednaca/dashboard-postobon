<?php

namespace App\Services\Fleet;

use App\Jobs\GenerateMediaPreviewJob;
use App\Models\Media;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class CompressedMediaPreview
{
    private const PROFILE = 'mp4-h264-448-15-crf28-v1';

    public function __construct(
        private readonly MediaPreviewSource $sources,
        private readonly MediaSourceMaterializer $materializer,
    ) {}

    public function url(Media $media): string
    {
        $original = route('media.content', $media);
        $identity = $this->identity($media);
        if ($identity === null || ! config('mediapreview.enabled')) {
            return $original;
        }
        $entry = Cache::get($this->key($identity), []);
        $fresh = isset($entry['checked_at']) && $entry['checked_at'] > now()->timestamp -
            ($entry['state'] === 'failed' ? config('mediapreview.retry_seconds') : config('mediapreview.remote_refresh_seconds'));
        if (! $fresh || (($entry['state'] ?? null) === 'ready' && $this->file($media, $entry['variant']) === null)) {
            $this->schedule($media, $identity);

            return $original;
        }

        return ($entry['state'] ?? null) === 'ready' && $this->file($media, $entry['variant']) !== null
            ? $original.'?preview='.$entry['variant'] : $original;
    }

    public function file(Media $media, string $variant): ?string
    {
        $identity = $this->identity($media);
        if ($identity === null || ! preg_match('/^[a-f0-9]{64}$/', $variant)) {
            return null;
        }
        $entry = Cache::get($this->key($identity), []);
        $path = 'media-previews/'.$identity.'/'.$variant.'.mp4';
        if (($entry['state'] ?? null) !== 'ready' || ($entry['variant'] ?? null) !== $variant
            || ! Storage::disk('local')->exists($path)) {
            return null;
        }
        $absolute = Storage::disk('local')->path($path);
        touch($absolute);

        return $absolute;
    }

    public function identity(Media $media): ?string
    {
        if ($media->mime_type !== 'video/mp4') {
            return null;
        }
        $source = $this->sources->resolve($media);
        if ($source === null || (int) $media->size > config('mediapreview.max_source_bytes')) {
            return null;
        }
        $version = [$media->id, self::PROFILE, $source['kind'], $source['path'], $media->size];
        if ($source['kind'] === 'local') {
            clearstatcache(true, $source['path']);
            $size = filesize($source['path']);
            if ($size === false || $size > config('mediapreview.max_source_bytes')) {
                return null;
            }
            $version[] = $size;
            $version[] = filemtime($source['path']);
        } else {
            $version[] = config('privatecloud.base_url');
        }

        return hash('sha256', json_encode($version, JSON_THROW_ON_ERROR));
    }

    private function schedule(Media $media, string $identity): void
    {
        $connection = (string) config('queue.default');
        $driver = config('queue.connections.'.$connection.'.driver');
        // Never run conversion inside the polling request, including sync/deferred drivers.
        if (! in_array($driver, ['database', 'redis'], true)
            || (int) config('queue.connections.'.$connection.'.retry_after', 0) <= 360
            || ! Cache::add($this->key($identity).':pending', true, 600)) {
            return;
        }
        try {
            GenerateMediaPreviewJob::dispatch($media->id, $identity);
        } catch (Throwable $exception) {
            Cache::forget($this->key($identity).':pending');
            $this->remember($identity, 'failed');
            Log::warning('No se pudo encolar el preview comprimido.', ['media_id' => $media->id,
                'exception_type' => $exception::class]);
        }
    }

    public function generate(int $mediaId, string $identity): void
    {
        $disk = Storage::disk('local');
        $directory = 'media-previews/'.$identity;
        $temporary = $directory.'/'.bin2hex(random_bytes(8));
        $input = $temporary.'.input.mp4';
        $output = $temporary.'.output.mp4';
        try {
            $media = Media::find($mediaId);
            if ($media === null || $this->identity($media) !== $identity) {
                return;
            }
            $size = $this->materializer->materialize($media, $input, (int) config('mediapreview.download_timeout'),
                (int) config('mediapreview.max_source_bytes'));
            if ($size > config('mediapreview.max_source_bytes')) {
                throw new RuntimeException('Source exceeds preview limit.');
            }
            $hash = hash_file('sha256', $disk->path($input));
            $variant = hash('sha256', self::PROFILE.$hash);
            $previous = Cache::get($this->key($identity), []);
            if (($previous['variant'] ?? null) === $variant && $disk->exists($directory.'/'.$variant.'.mp4')) {
                $this->remember($identity, 'ready', $variant);

                return;
            }
            $sourceMetadata = $this->probe($disk->path($input));
            if (! in_array($sourceMetadata['sar'], ['1:1', 'N/A'], true)) {
                throw new RuntimeException('Unsupported sample aspect ratio.');
            }
            $duration = $sourceMetadata['duration'];
            $result = Process::timeout((int) config('mediapreview.process_timeout'))->run([
                config('mediapreview.ffmpeg'), '-nostdin', '-y', '-hide_banner', '-loglevel', 'error',
                '-protocol_whitelist', 'file,pipe', '-enable_drefs', '0', '-use_absolute_path', '0',
                '-f', 'mov', '-i', $disk->path($input),
                '-map', '0:v:0', '-an', '-sn', '-dn', '-vf',
                'scale=448:448:force_original_aspect_ratio=decrease:force_divisible_by=2,setsar=1,fps=15',
                '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '28', '-pix_fmt', 'yuv420p',
                '-threads', '1', '-movflags', '+faststart', '-fs', (string) config('mediapreview.max_source_bytes'),
                '-f', 'mp4', $disk->path($output),
            ]);
            if (! $result->successful() || ! $disk->exists($output) || $disk->size($output) < 1) {
                throw new RuntimeException('Preview conversion failed.');
            }
            $metadata = $this->probe($disk->path($output));
            if ($metadata['codec'] !== 'h264' || max($metadata['width'], $metadata['height']) > 448
                || abs($metadata['duration'] - $duration) > 0.2) {
                throw new RuntimeException('Invalid preview output.');
            }
            $current = $media->fresh();
            if ($current === null || $this->identity($current) !== $identity) {
                return;
            }
            if ($disk->size($output) >= $size) {
                $this->remember($identity, 'original');

                return;
            }
            if (! $disk->move($output, $directory.'/'.$variant.'.mp4')) {
                throw new RuntimeException('Preview publication failed.');
            }
            $this->remember($identity, 'ready', $variant);
        } catch (Throwable) {
            $this->remember($identity, 'failed');
            Log::warning('No fue posible generar el preview comprimido.', ['media_id' => $mediaId]);
        } finally {
            $disk->delete([$input, $output]);
            Cache::forget($this->key($identity).':pending');
        }
    }

    private function probe(string $path): array
    {
        $result = Process::timeout(20)->run([
            config('mediapreview.ffprobe'), '-v', 'error', '-protocol_whitelist', 'file,pipe',
            '-enable_drefs', '0', '-use_absolute_path', '0', '-f', 'mov',
            '-i', $path, '-select_streams', 'v:0', '-show_entries',
            'stream=codec_name,width,height,duration,sample_aspect_ratio:format=duration', '-of', 'json',
        ]);
        $metadata = json_decode($result->output(), true);
        $stream = $metadata['streams'][0] ?? [];
        $duration = (float) ($stream['duration'] ?? $metadata['format']['duration'] ?? 0);
        if (! $result->successful() || $duration <= 0 || empty($stream['width']) || empty($stream['height'])) {
            throw new RuntimeException('Invalid MP4 video.');
        }

        return ['duration' => $duration, 'width' => (int) $stream['width'], 'height' => (int) $stream['height'],
            'codec' => $stream['codec_name'] ?? null, 'sar' => $stream['sample_aspect_ratio'] ?? 'N/A'];
    }

    private function remember(string $identity, string $state, ?string $variant = null): void
    {
        Cache::put($this->key($identity), compact('state', 'variant') + ['checked_at' => now()->timestamp], now()->addDays(7));
    }

    private function key(string $identity): string
    {
        return 'media-preview:'.$identity;
    }

    public function prune(): void
    {
        $disk = Storage::disk('local');
        $cutoff = now()->subDays((int) config('mediapreview.retention_days'))->timestamp;
        foreach ($disk->allFiles('media-previews') as $file) {
            if (preg_match('~^media-previews/[a-f0-9]{64}/[a-f0-9]+\.(mp4|input\.mp4|output\.mp4)$~', $file)
                && $disk->lastModified($file) < $cutoff) {
                $disk->delete($file);
            }
        }
    }
}
