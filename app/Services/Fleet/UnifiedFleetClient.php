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
    public function isConfigured(): bool
    {
        return filled(config('unifiedfleet.base_url')) && filled(config('unifiedfleet.token'));
    }

    public function getFleet(): array
    {
        return $this->send(
            fn (): Response => $this->request()->get('/api/fleet'),
            'consultar la flota unificada'
        );
    }

    public function sendCommand(array $payload): array
    {
        $this->ensureConfigured();

        return $this->send(
            fn (): Response => $this->request()->post('/api/fleet/commands', $payload),
            'enviar la orden a la flota'
        );
    }

    public function uploadAndDistribute(UploadedFile $file, array $targets): array
    {
        $this->ensureConfigured();

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
                fn (): Response => $this->request(true)
                    ->withHeaders([
                        'Content-Length' => (string) $size,
                        'X-File-Name' => rawurlencode($file->getClientOriginalName()),
                    ])
                    ->withBody($stream, 'video/mp4')
                    ->post('/api/fleet/uploads'),
                'subir el video al gateway unificado'
            );
        } finally {
            $stream->close();
        }

        $uploadId = $upload['upload_id'] ?? null;

        if (! is_string($uploadId) || $uploadId === '') {
            throw new RuntimeException('El gateway recibió el archivo pero no devolvió un identificador de carga.');
        }

        return $this->send(
            fn (): Response => $this->request(true)->post(
                '/api/fleet/uploads/'.rawurlencode($uploadId).'/distribute',
                ['targets' => array_values($targets)]
            ),
            'distribuir el video a los equipos seleccionados'
        );
    }

    private function request(bool $longRunning = false): PendingRequest
    {
        $request = Http::baseUrl(rtrim((string) config('unifiedfleet.base_url'), '/'))
            ->acceptJson()
            ->connectTimeout((int) config('unifiedfleet.connect_timeout', 10))
            ->timeout((int) config(
                $longRunning ? 'unifiedfleet.upload_timeout' : 'unifiedfleet.timeout',
                $longRunning ? 900 : 30
            ));

        $token = (string) config('unifiedfleet.token', '');

        return $token !== '' ? $request->withToken($token) : $request;
    }

    private function send(callable $operation, string $description): array
    {
        try {
            /** @var Response $response */
            $response = $operation();
        } catch (ConnectionException $exception) {
            throw new RuntimeException(
                "No fue posible {$description}: el gateway no respondió.",
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
}
