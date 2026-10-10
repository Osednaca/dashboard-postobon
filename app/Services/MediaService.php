<?php

namespace App\Services;

use App\Models\Media;
use App\Repositories\Contracts\MediaRepositoryInterface;
use App\Services\Z2\Z2VideoService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaService extends BaseService
{
    /**
     * MediaService constructor.
     */
    public function __construct(MediaRepositoryInterface $mediaRepository, private readonly Z2VideoService $videos)
    {
        parent::__construct($mediaRepository);
    }

    /**
     * Upload a file and create a media record.
     */
    public function upload(UploadedFile $file, ?string $name = null): Media
    {
        $disk = 'public';
        $path = $file->store('media', $disk);

        $media = $this->repository->create([
            'name' => $name ?? Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)),
            'original_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);

        /** @var Media */
        return $media;
    }

    /**
     * Delete a media file and its record.
     */
    public function delete(int|string $id): bool
    {
        $media = $this->repository->find($id);

        if ($media instanceof Media) {
            $filename = $this->videos->cloudFilename($media->file_path);
            if ($filename !== null && ! $this->videos->deleteVideo($filename)) {
                throw new \RuntimeException('La nube no confirmó la eliminación del archivo. El medio se conserva.');
            }
            $disk = Storage::disk('public');
            // Historical API uploads used the app's local disk. Never inspect a remote default disk.
            if ($filename === null && ! str_starts_with($media->file_path, 'http') && ! $disk->exists($media->file_path)
                && config('filesystems.disks.local.driver') === 'local'
                && Storage::disk('local')->exists($media->file_path)) {
                $disk = Storage::disk('local');
            }

            if ($filename === null && $media->file_path && ! str_starts_with($media->file_path, 'http')) {
                if ($disk->exists($media->file_path) && ! $disk->delete($media->file_path)) {
                    throw new \RuntimeException('No se pudo eliminar el archivo local. El medio se conserva.');
                }
            }

            if ($media->thumbnail && ! str_starts_with($media->thumbnail, 'http')) {
                try {
                    $posterDisk = Storage::disk('public');
                    if ($posterDisk->exists($media->thumbnail) && ! $posterDisk->delete($media->thumbnail)) {
                        Log::warning('No se pudo limpiar la miniatura de un medio eliminado.', ['media_id' => $media->id]);
                    }
                } catch (\Throwable $exception) {
                    Log::warning('No se pudo limpiar la miniatura de un medio eliminado.', ['media_id' => $media->id, 'error' => $exception->getMessage()]);
                }
            }
        }

        return parent::delete($id);
    }

    /**
     * Get media URL.
     */
    public function getUrl(int|string $id): ?string
    {
        $media = $this->repository->find($id);

        if ($media instanceof Media) {
            return Storage::disk(config('filesystems.default', 'public'))->url($media->file_path);
        }

        return null;
    }
}
