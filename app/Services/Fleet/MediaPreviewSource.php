<?php

namespace App\Services\Fleet;

use App\Models\Media;
use App\Services\Z2\Z2VideoService;
use Illuminate\Support\Facades\Storage;

class MediaPreviewSource
{
    private const RASTER_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif', 'image/bmp'];

    public function __construct(private readonly Z2VideoService $videos) {}

    /** @return array{kind: string, path: string, mime_type: string}|null */
    public function resolve(Media $media): ?array
    {
        $video = str_starts_with($media->mime_type, 'video/');
        if (! $video && ! in_array($media->mime_type, self::RASTER_TYPES, true)) {
            return null;
        }
        $path = $media->file_path;
        $filename = $video ? $this->videos->cloudFilename($path) : null;
        if ($filename !== null && str_starts_with($path, 'http')) {
            return ['kind' => 'private_cloud', 'path' => '/fileDownload/Videos/'.rawurlencode($filename), 'mime_type' => $media->mime_type];
        }
        if (preg_match('/[\\\\:\x00-\x1f]/', $path)
            || str_starts_with($path, '/')
            || array_intersect(explode('/', $path), ['', '.', '..'])) {
            return null;
        }

        foreach (['public', 'local'] as $name) {
            if (config('filesystems.disks.'.$name.'.driver') !== 'local') {
                continue;
            }
            $disk = Storage::disk($name);
            if (! $disk->exists($path)) {
                continue;
            }
            $root = realpath($disk->path(''));
            $file = realpath($disk->path($path));
            if ($root && $file && str_starts_with($file, $root.DIRECTORY_SEPARATOR) && is_file($file)) {
                $mime = $media->mime_type;
                if (! $video) {
                    $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file);
                    if (! in_array($mime, self::RASTER_TYPES, true) || @getimagesize($file) === false) {
                        return null;
                    }
                }

                return ['kind' => 'local', 'path' => $file, 'mime_type' => $mime];
            }

            return null;
        }

        if ($video && $filename !== null && filled(config('privatecloud.base_url'))) {
            return ['kind' => 'private_cloud', 'path' => '/fileDownload/Videos/'.rawurlencode($filename), 'mime_type' => $media->mime_type];
        }

        return null;
    }
}
