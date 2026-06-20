<?php

namespace App\Listeners;

use App\Events\MediaUploaded;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessUploadedMedia
{
    public function handle(MediaUploaded $event): void
    {
        $media = $event->media;

        try {
            $thumbnailPath = $this->generateThumbnail($media);

            if ($thumbnailPath) {
                $media->update(['thumbnail' => $thumbnailPath]);
            }

            Log::info("Miniatura generada para el medio: {$media->name}");
        } catch (\Throwable $e) {
            Log::error("Error al generar miniatura para {$media->name}: " . $e->getMessage());
        }
    }

    protected function generateThumbnail(\App\Models\Media $media): ?string
    {
        return null;
    }
}
