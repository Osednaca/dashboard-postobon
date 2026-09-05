<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\FleetCommandRequest;
use App\Http\Requests\FleetUploadRequest;
use App\Jobs\DistributeFleetVideoJob;
use App\Jobs\FormatFleetStorageJob;
use App\Models\FleetOperation;
use App\Models\FleetUpload;
use App\Services\Fleet\UnifiedFleetClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class UnifiedFleetController extends Controller
{
    public function __construct(private readonly UnifiedFleetClient $fleetClient) {}

    public function command(FleetCommandRequest $request): JsonResponse|RedirectResponse
    {
        $payload = $request->safe()->only([
            'command',
            'targets',
            'value',
            'wl35_video_index',
            'z2_filename',
        ]);

        if (in_array($payload['command'] ?? null, ['power', 'bluetooth'], true)) {
            $payload['value'] = $request->boolean('value');
        }

        if (($payload['command'] ?? null) === 'format_sd') {
            return $this->queueFormatOperation($request, $payload);
        }

        try {
            $result = $this->fleetClient->sendCommand($payload);

            return $this->redirectWithResult($result, 'orden');
        } catch (Throwable $exception) {
            Log::error('Falló una orden de flota unificada.', [
                'command' => $payload['command'] ?? null,
                'targets' => $payload['targets'] ?? [],
                'error' => $exception->getMessage(),
            ]);

            return back()->withInput()->with('error', $exception->getMessage());
        }
    }

    public function upload(FleetUploadRequest $request): JsonResponse|RedirectResponse
    {
        $file = $request->file('video');
        $targets = $request->validated('targets');
        $uploadId = (string) Str::uuid();
        $originalName = Str::limit($file->getClientOriginalName(), 250, '');
        $storedPath = null;
        $upload = null;

        try {
            if (in_array(config('queue.default'), ['sync', 'null'], true)) {
                throw new \RuntimeException('La distribución en segundo plano requiere QUEUE_CONNECTION=database o redis.');
            }

            $storedPath = $file->storeAs('fleet-uploads', $uploadId.'.mp4', 'local');

            if (! is_string($storedPath)) {
                throw new \RuntimeException('No fue posible guardar temporalmente el video recibido.');
            }

            $upload = FleetUpload::create([
                'id' => $uploadId,
                'user_id' => $request->user()->id,
                'original_name' => $originalName,
                'file_path' => $storedPath,
                'targets' => array_values($targets),
                'status' => 'queued',
                'phase' => 'queued',
                'progress' => 15,
            ]);

            DistributeFleetVideoJob::dispatch($upload->id);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'upload_id' => $upload->id,
                    'status_url' => route('fleet.upload.status', $upload),
                    'message' => 'El video fue recibido y su distribución continúa en segundo plano.',
                ], 202);
            }

            return back()->with('success', 'El video fue recibido y quedó en cola para distribuirse.');
        } catch (Throwable $exception) {
            $upload?->delete();

            if ($storedPath !== null) {
                Storage::disk('local')->delete($storedPath);
            }

            Log::error('Falló una distribución de video de la flota unificada.', [
                'filename' => $file?->getClientOriginalName(),
                'targets' => $targets,
                'error' => $exception->getMessage(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $exception->getMessage(),
                ], 500);
            }

            return back()->withInput()->with('error', $exception->getMessage());
        }
    }

    public function uploadStatus(Request $request, FleetUpload $fleetUpload): JsonResponse
    {
        abort_unless((int) $fleetUpload->user_id === (int) $request->user()->id, 403);

        return response()->json([
            'id' => $fleetUpload->id,
            'filename' => $fleetUpload->original_name,
            'status' => $fleetUpload->status,
            'phase' => $fleetUpload->phase,
            'progress' => $fleetUpload->progress,
            'error' => $fleetUpload->error,
            'result' => $fleetUpload->status === 'completed' ? $fleetUpload->result : null,
            'started_at' => $fleetUpload->started_at?->toIso8601String(),
            'completed_at' => $fleetUpload->completed_at?->toIso8601String(),
        ]);
    }

    public function operationStatus(Request $request, FleetOperation $fleetOperation): JsonResponse
    {
        abort_unless((int) $fleetOperation->user_id === (int) $request->user()->id, 403);

        return response()->json([
            'id' => $fleetOperation->id,
            'command' => $fleetOperation->command,
            'targets' => $fleetOperation->payload['targets'] ?? [],
            'status' => $fleetOperation->status,
            'progress' => $fleetOperation->progress,
            'error' => $fleetOperation->error,
            'result' => $fleetOperation->status === 'completed' ? $fleetOperation->result : null,
            'started_at' => $fleetOperation->started_at?->toIso8601String(),
            'completed_at' => $fleetOperation->completed_at?->toIso8601String(),
        ]);
    }

    private function queueFormatOperation(
        FleetCommandRequest $request,
        array $payload,
    ): JsonResponse|RedirectResponse {
        $operation = null;

        try {
            if (in_array(config('queue.default'), ['sync', 'null'], true)) {
                throw new \RuntimeException('El formateo en segundo plano requiere QUEUE_CONNECTION=database o redis.');
            }

            $operation = FleetOperation::create([
                'id' => (string) Str::uuid(),
                'user_id' => $request->user()->id,
                'command' => 'format_sd',
                'payload' => $payload,
                'status' => 'queued',
                'progress' => 10,
            ]);

            FormatFleetStorageJob::dispatch($operation->id);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'operation_id' => $operation->id,
                    'status_url' => route('fleet.operation.status', $operation),
                    'message' => 'El formateo quedó en cola y continuará en segundo plano.',
                ], 202);
            }

            return back()->with('success', 'El formateo quedó en cola y continuará en segundo plano.');
        } catch (Throwable $exception) {
            $operation?->delete();

            Log::error('No fue posible iniciar el formateo de la flota unificada.', [
                'targets' => $payload['targets'] ?? [],
                'error' => $exception->getMessage(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $exception->getMessage(),
                ], 500);
            }

            return back()->withInput()->with('error', $exception->getMessage());
        }
    }

    private function redirectWithResult(array $result, string $operation): RedirectResponse
    {
        $total = (int) ($result['total'] ?? count($result['results'] ?? []));
        $succeeded = (int) ($result['succeeded'] ?? 0);
        $failed = (int) ($result['failed'] ?? max(0, $total - $succeeded));
        $redirect = back()->with('fleet_results', $result['results'] ?? []);

        if ($failed === 0) {
            return $redirect->with(
                'success',
                ucfirst($operation)." completada en {$succeeded} de {$total} equipos."
            );
        }

        if ($succeeded > 0) {
            return $redirect->with(
                'warning',
                ucfirst($operation)." aceptada por {$succeeded} de {$total} equipos; {$failed} fallaron."
            );
        }

        return $redirect->with(
            'error',
            ucfirst($operation)." fallida en los {$failed} equipos seleccionados."
        );
    }
}
