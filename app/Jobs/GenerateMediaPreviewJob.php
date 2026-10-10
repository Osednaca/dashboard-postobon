<?php

namespace App\Jobs;

use App\Services\Fleet\CompressedMediaPreview;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateMediaPreviewJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 360;

    public int $tries = 1;

    public int $uniqueFor = 600;

    public function __construct(public readonly int $mediaId, public readonly string $identity)
    {
        $this->onQueue((string) config('mediapreview.queue'));
    }

    public function uniqueId(): string
    {
        return $this->identity;
    }

    public function handle(CompressedMediaPreview $previews): void
    {
        $previews->generate($this->mediaId, $this->identity);
    }
}
