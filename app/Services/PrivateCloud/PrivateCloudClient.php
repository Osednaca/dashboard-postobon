<?php

namespace App\Services\PrivateCloud;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente HTTP para la nube privada (fan-private-cloud).
 *
 * Sustituye por completo a FanCloudService (nube china Z2). Habla con la API
 * REST de la nube privada (/api/*) usando token Bearer opcional.
 */
class PrivateCloudClient
{
    private string $baseUrl;

    private string $token;

    private int $timeout;

    private int $connectTimeout;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('privatecloud.base_url', 'http://127.0.0.1:8080'), '/');
        $this->token = (string) config('privatecloud.token', '');
        $this->timeout = (int) config('privatecloud.timeout', 60);
        $this->connectTimeout = (int) config('privatecloud.connect_timeout', 10);
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    public function tokenConfigured(): bool
    {
        return $this->token !== '';
    }

    /**
     * Petición GET y decodificación JSON. Devuelve null si falla la red o el JSON.
     */
    public function get(string $path, array $query = []): ?array
    {
        return $this->send('GET', $path, ['query' => $query]);
    }

    /**
     * Petición POST JSON y decodificación. Devuelve null si falla.
     */
    public function post(string $path, array $data = []): ?array
    {
        return $this->send('POST', $path, ['json' => $data]);
    }

    /**
     * Subida de archivo multipart (campo "file").
     */
    public function postFile(string $path, string $filePath, string $filename, array $extra = []): ?array
    {
        try {
            $request = Http::baseUrl($this->baseUrl)
                ->timeout($this->timeout)
                ->connectTimeout($this->connectTimeout)
                ->acceptJson();

            if ($this->token !== '') {
                $request = $request->withToken($this->token);
            }

            $multipart = [];
            foreach ($extra as $key => $value) {
                $multipart[] = ['name' => $key, 'contents' => (string) $value];
            }
            $multipart[] = [
                'name' => 'file',
                'contents' => fopen($filePath, 'r'),
                'filename' => $filename,
            ];

            $response = $request->asMultipart()->post($path, $multipart);

            return $this->decode($response, $path);
        } catch (\Throwable $e) {
            Log::error('[PrivateCloud] Multipart request failed', ['path' => $path, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Petición DELETE.
     */
    public function delete(string $path): ?array
    {
        return $this->send('DELETE', $path);
    }

    /**
     * Verificación de conectividad contra /api/status.
     */
    public function ping(): bool
    {
        try {
            $response = Http::baseUrl($this->baseUrl)
                ->timeout($this->connectTimeout)
                ->connectTimeout($this->connectTimeout)
                ->get('/api/status');

            return $response->successful();
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function send(string $method, string $path, array $options = []): ?array
    {
        try {
            $request = Http::baseUrl($this->baseUrl)
                ->timeout($this->timeout)
                ->connectTimeout($this->connectTimeout)
                ->acceptJson();

            if ($this->token !== '') {
                $request = $request->withToken($this->token);
            }

            $response = $request->send($method, $path, $options);

            return $this->decode($response, $path);
        } catch (\Throwable $e) {
            Log::error('[PrivateCloud] Request failed', ['method' => $method, 'path' => $path, 'error' => $e->getMessage()]);

            return null;
        }
    }

    private function decode(Response $response, string $path): ?array
    {
        if (! $response->successful()) {
            Log::error('[PrivateCloud] Non-successful response', [
                'path' => $path,
                'status' => $response->status(),
                'body' => mb_strimwidth($response->body(), 0, 300, '...'),
            ]);

            return null;
        }

        $json = $response->json();
        if (! is_array($json)) {
            Log::error('[PrivateCloud] Invalid JSON response', ['path' => $path]);

            return null;
        }

        return $json;
    }
}
