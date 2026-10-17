<?php

namespace App\Jobs;

use App\Models\FleetUpload;
use App\Services\Fleet\FleetMediaPlaybackService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PlayFleetMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1900;

    public bool $failOnTimeout = true;

    public function __construct(public readonly string $fleetUploadId) {}

    public function handle(FleetMediaPlaybackService $playback): void
    {
        $upload = FleetUpload::with('sourceMedia')->findOrFail($this->fleetUploadId);

        if ($upload->status === 'completed') {
            return;
        }

        $upload->update([
            'status' => 'processing',
            'phase' => 'preparing_media',
            'progress' => 15,
            'started_at' => now(),
            'error' => null,
        ]);

        try {
            $media = $upload->sourceMedia;
            if ($media === null) {
                throw new \RuntimeException('El medio fue eliminado antes de iniciar la reproducción.');
            }

            $summary = $playback->play(
                $media,
                $upload->targets ?? [],
                $upload->original_name,
                $upload->file_path,
                fn (string $phase, int $progress) => $upload->update(compact('phase', 'progress')),
            );
            $upload->update([
                'status' => 'completed',
                'phase' => 'completed',
                'progress' => 100,
                'result' => $summary,
                'completed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $this->markAsFailed($exception->getMessage());
            throw $exception;
        } finally {
            Storage::disk('local')->delete($upload->file_path);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->markAsFailed($exception?->getMessage() ?? 'La reproducción instantánea se interrumpió.');

        $upload = FleetUpload::find($this->fleetUploadId);
        if ($upload !== null) {
            Storage::disk('local')->delete($upload->file_path);
        }
    }

    private function markAsFailed(string $message): void
    {
        $updated = FleetUpload::whereKey($this->fleetUploadId)
            ->whereNotIn('status', ['completed', 'failed'])
            ->update([
                'status' => 'failed',
                'phase' => 'failed',
                'error' => $message,
                'completed_at' => now(),
            ]);

        if ($updated > 0) {
            Log::error('Falló la reproducción de un medio de la flota.', [
                'fleet_upload_id' => $this->fleetUploadId,
                'error' => $message,
            ]);
        }
    }
}
