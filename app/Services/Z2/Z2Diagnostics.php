<?php

namespace App\Services\Z2;

use App\Services\PrivateCloud\PrivateCloudClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Diagnóstico de conexión con la nube privada (fan-private-cloud).
 */
class Z2Diagnostics
{
    private PrivateCloudClient $client;

    public function __construct(PrivateCloudClient $client)
    {
        $this->client = $client;
    }

    public function run(): array
    {
        $results = [];

        // 1. Check configuration
        $results['config'] = [
            'base_url' => config('privatecloud.base_url'),
            'token' => $this->client->tokenConfigured() ? 'SET' : 'NOT SET',
            'timeout' => config('privatecloud.timeout'),
        ];

        // 2. Test raw HTTP connectivity
        $results['http_connectivity'] = $this->testRawHttp();

        // 3. Test /api/status
        $results['status'] = $this->testStatus();

        // 4. Test login (ping + token)
        $results['auth'] = $this->testAuth();

        // 5. Test device list
        $results['device_list'] = $this->testDeviceList();

        // 6. Test media list
        $results['media_list'] = $this->testMediaList();

        return $results;
    }

    private function testRawHttp(): array
    {
        try {
            $base = rtrim((string) config('privatecloud.base_url', ''), '/');
            if ($base === '') {
                return ['http_code' => null, 'has_error' => true, 'error' => 'PRIVATE_CLOUD_URL no configurada'];
            }

            $ch = curl_init($base.'/api/status');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HEADER, true);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            return [
                'http_code' => $httpCode,
                'has_error' => ! empty($error),
                'error' => $error,
                'response_length' => strlen((string) $response),
            ];
        } catch (\Throwable $e) {
            return [
                'http_code' => null,
                'has_error' => true,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function testStatus(): array
    {
        try {
            $response = $this->client->get('/api/status');

            if ($response === null) {
                return ['success' => false, 'error' => 'Respuesta inválida o nula'];
            }

            return [
                'success' => ($response['ok'] ?? false) === true,
                'status' => $response['ok'] ?? null,
                'devices' => $response['devices'] ?? null,
                'online' => $response['online'] ?? null,
                'raw_keys' => array_keys($response),
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function testAuth(): array
    {
        try {
            $login = $this->client->ping();

            return [
                'success' => $login,
                'authenticated' => $login,
                'token' => $this->client->tokenConfigured() ? 'SET' : 'NOT SET',
                'error' => ! $login ? 'No se pudo conectar con la nube privada' : null,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function testDeviceList(): array
    {
        try {
            $response = $this->client->get('/api/devices');

            if ($response === null) {
                return ['has_response' => false, 'error' => 'Respuesta inválida'];
            }

            $devices = $response['devices'] ?? [];

            return [
                'has_response' => true,
                'count' => count($devices),
                'first_device' => isset($devices[0]) ? array_keys($devices[0]) : [],
                'raw_keys' => array_keys($response),
            ];
        } catch (\Throwable $e) {
            return ['has_response' => false, 'error' => $e->getMessage()];
        }
    }

    private function testMediaList(): array
    {
        try {
            $response = $this->client->get('/api/media');

            if ($response === null) {
                return ['has_response' => false, 'error' => 'Respuesta inválida'];
            }

            $media = $response['media'] ?? [];

            return [
                'has_response' => true,
                'count' => count($media),
                'first_file' => $media[0]['filename'] ?? null,
            ];
        } catch (\Throwable $e) {
            return ['has_response' => false, 'error' => $e->getMessage()];
        }
    }
}