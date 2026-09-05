<?php

namespace App\Services\Fleet;

use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class UnifiedFleetClient
{
    private ?string $resolvedBaseUrl = null;

    public function baseUrl(): string
    {
        return rtrim((string) config('unifiedfleet.base_url'), '/');
    }

    public function isConfigured(): bool
    {
        return filled(config('unifiedfleet.base_url')) && filled(config('unifiedfleet.token'));
    }

    public function getFleet(): array
    {
        $errors = [];

        foreach ($this->candidateBaseUrls() as $baseUrl) {
            try {
                $fleet = $this->send(
                    fn (): Response => $this->request(false, $baseUrl)->get('/api/fleet'),
                    'consultar la flota unificada',
                    $baseUrl
                );
                $this->resolvedBaseUrl = $baseUrl;

                return $fleet;
            } catch (RuntimeException $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        throw new RuntimeException(implode(' ', array_unique($errors)));
    }

    public function sendCommand(array $payload): array
    {
        $this->ensureConfigured();
        $baseUrl = $this->resolveReachableBaseUrl();

        return $this->send(
            fn (): Response => $this->request(false, $baseUrl)->post('/api/fleet/commands', $payload),
            'enviar la orden a la flota',
            $baseUrl
        );
    }

    public function uploadAndDistribute(UploadedFile $file, array $targets): array
    {
        $this->ensureConfigured();
        $baseUrl = $this->resolveReachableBaseUrl();

        $path = $file->getRealPath();
        $size = $file->getSize();

        if ($path === false || $size === false) {
            throw new RuntimeException('No fue posible leer el archivo temporal recibido.');
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('No fue posible abrir el video para enviarlo al gateway.');
        }

        $stream = Utils::streamFor($handle);

        try {
            $upload = $this->send(
                fn (): Response => $this->request(true, $baseUrl)
                    ->withHeaders([
                        'Content-Length' => (string) $size,
                        'X-File-Name' => rawurlencode($file->getClientOriginalName()),
                    ])
                    ->withBody($stream, 'video/mp4')
                    ->post('/api/fleet/uploads'),
                'subir el video al gateway unificado',
                $baseUrl
            );
        } finally {
            $stream->close();
        }

        $uploadId = $upload['upload_id'] ?? null;

        if (! is_string($uploadId) || $uploadId === '') {
            throw new RuntimeException('El gateway recibió el archivo pero no devolvió un identificador de carga.');
        }

        return $this->send(
            fn (): Response => $this->request(true, $baseUrl)->post(
                '/api/fleet/uploads/'.rawurlencode($uploadId).'/distribute',
                ['targets' => array_values($targets)]
            ),
            'distribuir el video a los equipos seleccionados',
            $baseUrl
        );
    }

    private function request(bool $longRunning = false, ?string $baseUrl = null): PendingRequest
    {
        $request = Http::baseUrl($baseUrl ?? $this->baseUrl())
            ->acceptJson()
            ->connectTimeout((int) config('unifiedfleet.connect_timeout', 10))
            ->timeout((int) config(
                $longRunning ? 'unifiedfleet.upload_timeout' : 'unifiedfleet.timeout',
                $longRunning ? 900 : 30
            ));

        $token = (string) config('unifiedfleet.token', '');

        return $token !== '' ? $request->withToken($token) : $request;
    }

    private function send(callable $operation, string $description, ?string $baseUrl = null): array
    {
        try {
            /** @var Response $response */
            $response = $operation();
        } catch (ConnectionException $exception) {
            $target = $baseUrl ?? $this->baseUrl();
            throw new RuntimeException(
                "El gateway WL35 no está accesible en {$target}. Verifica que dashboard-fan-wl35 esté ejecutándose y que UNIFIED_FLEET_API_URL apunte a su puerto HTTP.",
                previous: $exception
            );
        }

        $payload = $response->json();

        if (! $response->successful()) {
            $message = is_array($payload)
                ? ($payload['error'] ?? $payload['message'] ?? null)
                : null;

            throw new RuntimeException(
                is_string($message) && $message !== ''
                    ? $message
                    : "No fue posible {$description} (HTTP {$response->status()})."
            );
        }

        if (! is_array($payload)) {
            throw new RuntimeException("El gateway devolvió una respuesta inválida al {$description}.");
        }

        return $payload;
    }

    private function ensureConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException(
                'Configura UNIFIED_FLEET_API_URL y UNIFIED_FLEET_API_TOKEN en el archivo .env.'
            );
        }
    }

    /**
     * Resuelve el gateway con una consulta de solo lectura antes de cualquier
     * operación mutable. Así se puede usar el host alternativo sin duplicar
     * accidentalmente una orden o una carga.
     */
    private function resolveReachableBaseUrl(): string
    {
        if ($this->resolvedBaseUrl !== null) {
            return $this->resolvedBaseUrl;
        }

        $errors = [];
        foreach ($this->candidateBaseUrls() as $baseUrl) {
            try {
                $this->send(
                    fn (): Response => $this->request(false, $baseUrl)->get('/api/fleet'),
                    'comprobar el gateway unificado',
                    $baseUrl
                );
                $this->resolvedBaseUrl = $baseUrl;

                return $baseUrl;
            } catch (RuntimeException $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        throw new RuntimeException(implode(' ', array_unique($errors)));
    }

    /** @return array<int, string> */
    private function candidateBaseUrls(): array
    {
        return array_values(array_unique(array_filter([
            $this->baseUrl(),
            rtrim((string) config('unifiedfleet.fallback_base_url'), '/'),
        ])));
    }
}
