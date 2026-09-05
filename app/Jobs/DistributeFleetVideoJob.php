<?php

namespace App\Jobs;

use App\Models\FleetUpload;
use App\Services\Fleet\UnifiedFleetClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DistributeFleetVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1900;

    public bool $failOnTimeout = true;

    public function __construct(public readonly string $fleetUploadId) {}

    public function handle(UnifiedFleetClient $fleetClient): void
    {
        $upload = FleetUpload::findOrFail($this->fleetUploadId);

        if ($upload->status === 'completed') {
            return;
        }

        $upload->update([
            'status' => 'processing',
            'phase' => 'uploading_gateway',
            'progress' => 20,
            'started_at' => now(),
            'error' => null,
        ]);

        try {
            $result = $fleetClient->uploadPathAndDistribute(
                Storage::disk('local')->path($upload->file_path),
                $upload->original_name,
                $upload->targets,
                function (string $phase, int $progress) use ($upload): void {
                    $upload->update(compact('phase', 'progress'));
                },
            );

            $upload->update([
                'status' => 'completed',
                'phase' => 'completed',
                'progress' => 100,
                'result' => $result,
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
        $this->markAsFailed($exception?->getMessage() ?? 'El proceso de distribución se interrumpió.');

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
            Log::error('Falló el trabajo de distribución de video.', [
                'fleet_upload_id' => $this->fleetUploadId,
                'error' => $message,
            ]);
        }
    }
}
