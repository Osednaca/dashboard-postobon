<?php

namespace App\Services\Fleet;

use App\Models\Media;
use Illuminate\Support\Facades\Storage;

class MediaPreviewSource
{
    /** @return array{kind: string, path: string}|null */
    public function resolve(Media $media): ?array
    {
        $path = $media->file_path;
        if (! str_starts_with($media->mime_type, 'video/')
            || preg_match('/[\\\\:\x00-\x1f]/', $path)
            || str_starts_with($path, '/')
            || array_intersect(explode('/', $path), ['', '.', '..'])) {
            return null;
        }

        $disk = Storage::disk('public');
        if ($disk->exists($path)) {
            $root = realpath($disk->path(''));
            $file = realpath($disk->path($path));
            if ($root && $file && str_starts_with($file, $root.DIRECTORY_SEPARATOR) && is_file($file)) {
                return ['kind' => 'local', 'path' => $file];
            }

            return null;
        }

        if (! str_contains($path, '/') && filled(config('privatecloud.base_url'))) {
            return ['kind' => 'private_cloud', 'path' => '/fileDownload/Videos/'.rawurlencode($path)];
        }

        return null;
    }
}
