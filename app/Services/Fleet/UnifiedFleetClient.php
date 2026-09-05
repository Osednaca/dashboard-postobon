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

    public function sendCommand(array $payload, bool $longRunning = false): array
    {
        $this->ensureConfigured();
        $baseUrl = $this->resolveReachableBaseUrl();

        return $this->send(
            fn (): Response => $this->request(
                $longRunning,
                $baseUrl,
                $longRunning ? (int) config('unifiedfleet.operation_timeout', 1800) : null,
            )->post('/api/fleet/commands', $payload),
            'enviar la orden a la flota',
            $baseUrl
        );
    }

    public function uploadAndDistribute(UploadedFile $file, array $targets): array
    {
        $path = $file->getRealPath();

        if ($path === false) {
            throw new RuntimeException('No fue posible leer el archivo temporal recibido.');
        }

        return $this->uploadPathAndDistribute(
            $path,
            $file->getClientOriginalName(),
            $targets,
        );
    }

    /**
     * Upload a persisted file and distribute it without depending on the
     * lifecycle of the original HTTP request.
     *
     * @param array<int, string> $targets
     */
    public function uploadPathAndDistribute(
        string $path,
        string $originalName,
        array $targets,
        ?callable $progress = null,
    ): array {
        $this->ensureConfigured();
        $baseUrl = $this->resolveReachableBaseUrl();
        $size = filesize($path);

        if ($size === false) {
            throw new RuntimeException('No fue posible leer el video almacenado para enviarlo al gateway.');
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('No fue posible abrir el video para enviarlo al gateway.');
        }

        $stream = Utils::streamFor($handle);
        if ($progress !== null) {
            $progress('uploading_gateway', 25);
        }

        try {
            $upload = $this->send(
                fn (): Response => $this->request(true, $baseUrl)
                    ->withHeaders([
                        'Content-Length' => (string) $size,
                        'X-File-Name' => rawurlencode($originalName),
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

        if ($progress !== null) {
            $progress('distributing', 65);
        }

        $result = $this->send(
            fn (): Response => $this->request(true, $baseUrl)->post(
                '/api/fleet/uploads/'.rawurlencode($uploadId).'/distribute',
                ['targets' => array_values($targets)]
            ),
            'distribuir el video a los equipos seleccionados',
            $baseUrl
        );

        if ($progress !== null) {
            $progress('finalizing', 92);
        }

        return $result;
    }

    private function request(
        bool $longRunning = false,
        ?string $baseUrl = null,
        ?int $timeout = null,
    ): PendingRequest
    {
        $request = Http::baseUrl($baseUrl ?? $this->baseUrl())
            ->acceptJson()
            ->connectTimeout((int) config('unifiedfleet.connect_timeout', 10))
            ->timeout($timeout ?? (int) config(
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
