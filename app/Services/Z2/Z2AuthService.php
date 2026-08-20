<?php

namespace App\Services\Z2;

use App\Services\PrivateCloud\PrivateCloudClient;
use Illuminate\Support\Facades\Cache;

/**
 * Autenticación contra la nube privada (fan-private-cloud).
 *
 * La nube privada usa un token Bearer opcional (no sesiones JSESSIONID), así
 * que "login" equivale a verificar conectividad y "checkSession" a un ping.
 */
class Z2AuthService
{
    private PrivateCloudClient $client;

    public function __construct(PrivateCloudClient $client)
    {
        $this->client = $client;
    }

    /**
     * Verifica la conexión con la nube privada.
     */
    public function login(): ?array
    {
        if ($this->client->ping()) {
            return [
                'authenticated' => true,
                'base_url' => $this->client->baseUrl(),
                'token' => $this->client->tokenConfigured() ? 'SET' : 'NOT SET',
            ];
        }

        return null;
    }

    /**
     * Comprueba que la nube privada responda.
     */
    public function checkSession(): bool
    {
        return $this->client->ping();
    }

    /**
     * No hay sesión que cerrar; se limpia la caché por compatibilidad.
     */
    public function logout(): void
    {
        Cache::forget('z2_session_cookies');
        Cache::forget('z2_advertiser_id');
    }
}