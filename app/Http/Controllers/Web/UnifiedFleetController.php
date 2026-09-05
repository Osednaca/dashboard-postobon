<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\FleetCommandRequest;
use App\Http\Requests\FleetUploadRequest;
use App\Services\Fleet\UnifiedFleetClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class UnifiedFleetController extends Controller
{
    public function __construct(private readonly UnifiedFleetClient $fleetClient) {}

    public function index(): View
    {
        $fleet = [
            'devices' => [],
            'media' => [],
            'sources' => [],
        ];
        $gatewayError = null;

        try {
            $fleet = array_merge($fleet, $this->fleetClient->getFleet());
        } catch (Throwable $exception) {
            $gatewayError = $exception->getMessage();
            Log::warning('No se pudo consultar la flota unificada.', [
                'error' => $exception->getMessage(),
            ]);
        }

        return view('fleet.index', [
            'devices' => collect($fleet['devices'] ?? [])->values(),
            'media' => collect($fleet['media'] ?? [])->values(),
            'sources' => $fleet['sources'] ?? [],
            'gatewayError' => $gatewayError,
            'gatewayConfigured' => $this->fleetClient->isConfigured(),
        ]);
    }

    public function command(FleetCommandRequest $request): RedirectResponse
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

    public function upload(FleetUploadRequest $request): RedirectResponse
    {
        $file = $request->file('video');
        $targets = $request->validated('targets');

        try {
            $result = $this->fleetClient->uploadAndDistribute($file, $targets);

            return $this->redirectWithResult($result, 'distribución de video');
        } catch (Throwable $exception) {
            Log::error('Falló una distribución de video de la flota unificada.', [
                'filename' => $file?->getClientOriginalName(),
                'targets' => $targets,
                'error' => $exception->getMessage(),
            ]);

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
