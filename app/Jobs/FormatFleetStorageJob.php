<?php

namespace App\Jobs;

use App\Models\FleetOperation;
use App\Models\Wl35DeviceMedia;
use App\Services\Fleet\UnifiedFleetClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class FormatFleetStorageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1900;

    public bool $failOnTimeout = true;

    public function __construct(public readonly string $operationId) {}

    public function handle(UnifiedFleetClient $fleetClient): void
    {
        $operation = FleetOperation::findOrFail($this->operationId);

        if ($operation->status === 'completed') {
            return;
        }

        $operation->update([
            'status' => 'processing',
            'progress' => 25,
            'started_at' => now(),
            'error' => null,
        ]);

        try {
            $result = $fleetClient->sendCommand($operation->payload, true);

            collect($result['results'] ?? [])
                ->filter(fn ($item): bool => is_array($item)
                    && ($item['success'] ?? false) === true
                    && ($item['type'] ?? null) === 'wl35')
                ->each(function (array $item): void {
                    Wl35DeviceMedia::where('device_id', (string) ($item['id'] ?? ''))->delete();
                });

            $operation->update([
                'status' => 'completed',
                'progress' => 100,
                'result' => $result,
                'completed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $this->markAsFailed($exception->getMessage());
            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->markAsFailed($exception?->getMessage() ?? 'El formateo masivo se interrumpió.');
    }

    private function markAsFailed(string $message): void
    {
        $updated = FleetOperation::whereKey($this->operationId)
            ->whereNotIn('status', ['completed', 'failed'])
            ->update([
                'status' => 'failed',
                'progress' => 100,
                'error' => $message,
                'completed_at' => now(),
            ]);

        if ($updated > 0) {
            Log::error('Falló el formateo de la flota unificada.', [
                'fleet_operation_id' => $this->operationId,
                'error' => $message,
            ]);
        }
    }
}
