<?php

namespace App\Services\Z2;

class Z2AuthService
{
    private FanCloudService $client;

    public function __construct(FanCloudService $client)
    {
        $this->client = $client;
    }

    /**
     * Authenticate and return user info.
     */
    public function login(): ?array
    {
        if ($this->client->isAuthenticated()) {
            return ['authenticated' => true];
        }

        $result = $this->client->authenticate();
        if ($result) {
            return ['authenticated' => true, 'session_id' => $this->client->getSessionId()];
        }

        return null;
    }

    /**
     * Check if session is valid.
     */
    public function checkSession(): bool
    {
        return $this->client->isAuthenticated();
    }

    /**
     * Logout and clear session.
     */
    public function logout(): void
    {
        // No explicit logout endpoint documented, just clear local session
        \Illuminate\Support\Facades\Cache::forget('z2_session_cookies');
        \Illuminate\Support\Facades\Cache::forget('z2_advertiser_id');
    }
}
