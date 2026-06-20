<?php

namespace App\Services;

use App\Models\Media;
use App\Repositories\Contracts\MediaRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaService extends BaseService
{
    /**
     * MediaService constructor.
     *
     * @param MediaRepositoryInterface $mediaRepository
     */
    public function __construct(MediaRepositoryInterface $mediaRepository)
    {
        parent::__construct($mediaRepository);
    }

    /**
     * Upload a file and create a media record.
     *
     * @param UploadedFile $file
     * @param string|null $name
     * @return Media
     */
    public function upload(UploadedFile $file, ?string $name = null): Media
    {
        $disk = config('filesystems.default', 'public');
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
     *
     * @param int|string $id
     * @return bool
     */
    public function delete(int|string $id): bool
    {
        $media = $this->repository->find($id);

        if ($media instanceof Media) {
            Storage::disk(config('filesystems.default', 'public'))->delete($media->file_path);
        }

        return parent::delete($id);
    }

    /**
     * Get media URL.
     *
     * @param int|string $id
     * @return string|null
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
