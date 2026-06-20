<?php

namespace App\Repositories;

use App\Models\Media;
use App\Repositories\Contracts\MediaRepositoryInterface;

class MediaRepository extends BaseRepository implements MediaRepositoryInterface
{
    /**
     * MediaRepository constructor.
     *
     * @param Media $media
     */
    public function __construct(Media $media)
    {
        parent::__construct($media);
    }
}
