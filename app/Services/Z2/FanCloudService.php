<?php

namespace App\Services\Z2;

use App\Models\ApiLog;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;

class FanCloudService
{
    private Client $httpClient;
    private CookieJar $cookieJar;
    private string $baseUrl;
    public string $username;
    private string $password;
    private int $maxRetries;
    private int $timeout;
    private bool $isAuthenticated = false;

    public function __construct()
    {
        $this->baseUrl = config('z2.base_url', 'http://www.holographicdisplay.cn:8088');
            $this->username = strtolower(config('z2.username', ''));
            $this->password = config('z2.password', '');
        $this->maxRetries = config('z2.retries', 3);
        $this->timeout = config('z2.timeout', 30);

        $this->cookieJar = new CookieJar();
        $this->httpClient = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => $this->timeout,
            'connect_timeout' => 10,
            'cookies' => $this->cookieJar,
            'http_errors' => false,
            'verify' => false,
        ]);

        // Restore session from cache if available
        $this->restoreSession();
    }

    /**
     * Authenticate with RSA login flow.
     */
    public function authenticate(): bool
    {
        try {
            Log::info('[Z2] Starting RSA authentication flow');

            // If we already have a session, validate it first
            if ($this->getSessionId()) {
                Log::info('[Z2] Existing session found, validating...');
                $test = $this->request('POST', '/User/groupList', [
                    'userName' => $this->username,
                ], true, 1, true);

                if ($test !== null && isset($test['aaData'])) {
                    $this->isAuthenticated = true;
                    Log::info('[Z2] Session validated successfully');
                    return true;
                }

                Log::warning('[Z2] Session expired or invalid, clearing and re-authenticating');
                $this->clearSession();
            }

            // Step 1: Get RSA public key
            $keyData = $this->getRsaPublicKey();
            if (! $keyData) {
                Log::error('[Z2] Failed to obtain RSA public key');
                return false;
            }

            // Step 2: Encrypt password
            $encryptedPassword = $this->encryptPasswordRsa($this->password, $keyData);
            if (! $encryptedPassword) {
                Log::error('[Z2] Failed to encrypt password');
                return false;
            }

            // Step 3: Login
            $response = $this->login($encryptedPassword);
            if ($response && ($response['result'] ?? -1) === 0) {
                $this->isAuthenticated = true;
                $this->saveSession();
                if (isset($response['adVertisers']['idAdvertiser'])) {
                    $this->setAdvertiserId((string) $response['adVertisers']['idAdvertiser']);
                }
                Log::info('[Z2] Authentication successful', [
                    'user' => $response['adVertisers']['name'] ?? 'unknown',
                    'advertiserId' => $response['adVertisers']['idAdvertiser'] ?? null,
                ]);
                return true;
            }

            Log::error('[Z2] Login failed', ['response' => $response]);
            return false;
        } catch (\Throwable $e) {
            Log::error('[Z2] Authentication error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get RSA public key from server.
     */
    private function getRsaPublicKey(): ?array
    {
        $response = $this->request('GET', '/admin/AdminLoginR', [], false);
        if ($response && isset($response['pubmodules_base64'])) {
            return $response;
        }
        return null;
    }

    /**
     * Encrypt password using RSA public key with NO padding.
     *
     * The Z2 API uses textbook RSA (RSA_NO_PADDING):
     * 1. Create a 128-byte block (1024-bit key)
     * 2. Place password at the END of the block (front-zero-padded)
     * 3. Encrypt with RSA_NO_PADDING
     * 4. Convert ciphertext to hex
     */
    private function encryptPasswordRsa(string $password, array $keyData): ?string
    {
        try {
            $pubModulesBase64 = $keyData['pubmodules_base64'] ?? '';
            if (empty($pubModulesBase64)) {
                return null;
            }

            $clean = str_replace(["\r", "\n"], '', $pubModulesBase64);
            $pem = "-----BEGIN PUBLIC KEY-----\n"
                 . chunk_split($clean, 64, "\n")
                 . "-----END PUBLIC KEY-----";

            $key = PublicKeyLoader::load($pem)->withPadding(RSA::ENCRYPTION_NONE);

            // 128-byte block for 1024-bit RSA, password right-justified (zero-padded at front)
            $blockSize = 128;
            $data = str_repeat("\x00", $blockSize - strlen($password)) . $password;
            $encrypted = $key->encrypt($data);

            return bin2hex($encrypted);
        } catch (\Throwable $e) {
            Log::error('[Z2] Password encryption error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Perform login with encrypted password.
     */
    private function login(string $encryptedPassword): ?array
    {
        $response = $this->request('POST', '/User/loginR', [
            'userName' => $this->username,
            'password' => $encryptedPassword,
            'loginType' => '1',
        ], false);

        return $response;
    }

    /**
     * Make authenticated request with automatic retry and session renewal.
     */
    public function request(string $method, string $endpoint, array $data = [], bool $requiresAuth = true, int $attempt = 1, bool $skipAuthCheck = false): ?array
    {
        $startTime = microtime(true);
        $service = class_basename(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['class'] ?? 'FanCloudService');

        try {
            // Ensure authentication for protected endpoints
            if ($requiresAuth && ! $this->isAuthenticated && ! $skipAuthCheck) {
                if (! $this->authenticate()) {
                    return null;
                }
            }

            $options = [
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'okhttp-okgo/jeasonlzy',
                ],
            ];

            if (strtoupper($method) === 'GET') {
                $options['query'] = $data;
            } else {
                $options['form_params'] = $data;
            }

            $response = $this->httpClient->request($method, $endpoint, $options);
            $statusCode = $response->getStatusCode();
            $body = (string) $response->getBody();
            $json = json_decode($body, true);

            $duration = (int) ((microtime(true) - $startTime) * 1000);

            // Check for session expiry
            if ($requiresAuth && $this->isSessionExpired($json)) {
                Log::warning('[Z2] Session expired, re-authenticating', ['endpoint' => $endpoint]);
                $this->isAuthenticated = false;
                $this->clearSession();

                if ($attempt < $this->maxRetries) {
                    return $this->request($method, $endpoint, $data, $requiresAuth, $attempt + 1);
                }
            }

            $this->logApiCall($service, $endpoint, $method, $data, $json, $statusCode, $attempt, true, null, $duration);

            return $json;
        } catch (RequestException $e) {
            $duration = (int) ((microtime(true) - $startTime) * 1000);
            $errorMessage = $e->getMessage();
            $statusCode = $e->getResponse()?->getStatusCode();

            $this->logApiCall($service, $endpoint, $method, $data, null, $statusCode, $attempt, false, $errorMessage, $duration);

            Log::error("[Z2] Request failed: {$endpoint}", [
                'error' => $errorMessage,
                'attempt' => $attempt,
            ]);

            // Retry with exponential backoff
            if ($attempt < $this->maxRetries) {
                $delay = pow(2, $attempt - 1) * 1000; // 1s, 2s, 4s
                usleep($delay * 1000);
                return $this->request($method, $endpoint, $data, $requiresAuth, $attempt + 1);
            }

            return null;
        } catch (\Throwable $e) {
            $duration = (int) ((microtime(true) - $startTime) * 1000);
            $this->logApiCall($service, $endpoint, $method, $data, null, null, $attempt, false, $e->getMessage(), $duration);
            Log::error("[Z2] Unexpected error: {$endpoint} - " . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if response indicates session expiry.
     */
    private function isSessionExpired(?array $response): bool
    {
        if ($response === null) {
            return false;
        }

        // Check common session expiry patterns
        if (isset($response['result']) && ($response['result'] === -1 || $response['result'] === 3)) {
            return true;
        }

        if (isset($response['message']) && str_contains(strtolower($response['message']), 'session')) {
            return true;
        }

        return false;
    }

    /**
     * Log API call to database.
     */
    private function logApiCall(
        string $service,
        string $endpoint,
        string $method,
        array $request,
        ?array $response,
        ?int $statusCode,
        int $attempt,
        bool $success,
        ?string $error,
        int $duration
    ): void {
        try {
            ApiLog::create([
                'service' => $service,
                'endpoint' => $endpoint,
                'method' => $method,
                'request_body' => json_encode($request),
                'response_body' => $response ? json_encode($response) : null,
                'status_code' => $statusCode,
                'attempt' => $attempt,
                'success' => $success,
                'error_message' => $error,
                'duration_ms' => $duration,
                'ip_address' => request()->ip(),
            ]);
        } catch (\Throwable $e) {
            Log::error('[Z2] Failed to log API call: ' . $e->getMessage());
        }
    }

    /**
     * Save session cookies to cache.
     */
    public function saveSession(): void
    {
        $cookies = [];
        foreach ($this->cookieJar->toArray() as $cookie) {
            $cookies[] = $cookie;
        }
        Cache::put('z2_session_cookies', $cookies, now()->addHours(12));
    }

    /**
     * Restore session cookies from cache.
     */
    private function restoreSession(): void
    {
        $cookies = Cache::get('z2_session_cookies');
        if (is_array($cookies) && count($cookies) > 0) {
            $this->cookieJar = new CookieJar(false, $cookies);
            $this->httpClient = new Client([
                'base_uri' => $this->baseUrl,
                'timeout' => $this->timeout,
                'connect_timeout' => 10,
                'cookies' => $this->cookieJar,
                'http_errors' => false,
                'verify' => false,
            ]);
            // Don't mark as authenticated here - let authenticate() validate the session first
            $this->isAuthenticated = false;
        }
    }

    /**
     * Clear session from cache.
     */
    private function clearSession(): void
    {
        Cache::forget('z2_session_cookies');
        Cache::forget('z2_advertiser_id');
        $this->isAuthenticated = false;
        $this->cookieJar = new CookieJar();
        $this->httpClient = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => $this->timeout,
            'connect_timeout' => 10,
            'cookies' => $this->cookieJar,
            'http_errors' => false,
            'verify' => false,
        ]);
    }

    /**
     * Get the current JSESSIONID.
     */
    public function getSessionId(): ?string
    {
        foreach ($this->cookieJar->toArray() as $cookie) {
            if ($cookie['Name'] === 'JSESSIONID') {
                return $cookie['Value'];
            }
        }
        return null;
    }

    /**
     * Check if currently authenticated.
     */
    public function isAuthenticated(): bool
    {
        return $this->isAuthenticated;
    }

    /**
     * Get the advertiser ID from authenticated session.
     */
    public function getAdvertiserId(): ?string
    {
        return Cache::get('z2_advertiser_id');
    }

    /**
     * Mark service as authenticated (used after manual login).
     */
    public function markAuthenticated(): void
    {
        $this->isAuthenticated = true;
    }

    /**
     * Store advertiser ID.
     */
    public function setAdvertiserId(string $id): void
    {
        Cache::put('z2_advertiser_id', $id, now()->addHours(12));
    }
}
