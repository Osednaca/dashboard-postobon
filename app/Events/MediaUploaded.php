<?php

namespace App\Events;

use App\Models\Media;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MediaUploaded
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Media $media;

    public function __construct(Media $media)
    {
        $this->media = $media;
    }
}
