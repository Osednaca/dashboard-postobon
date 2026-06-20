<?php

namespace App\Services\External\Z2;

use App\Exceptions\Z2ApiException;
use App\Exceptions\Z2AuthenticationException;
use App\Exceptions\Z2SessionExpiredException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\Utils;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Main service class for Z2 FanCloud API integration.
 *
 * Handles session management, RSA login, automatic retries, and request logging.
 */
class FanCloudService
{
    protected Client $client;

    protected string $baseUrl;

    protected string $username;

    protected string $password;

    protected string $rsaPublicKey;

    protected int $timeout;

    protected int $connectTimeout;

    protected int $retries;

    /**
     * @var int[]
     */
    protected array $retryDelay;

    protected string $sessionCacheKey;

    protected int $sessionCacheTtl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('z2.base_url', 'https://api.z2.com'), '/');
        $this->username = config('z2.username', '');
        $this->password = config('z2.password', '');
        $this->rsaPublicKey = config('z2.rsa_public_key', '');
        $this->timeout = (int) config('z2.timeout', 30);
        $this->connectTimeout = (int) config('z2.connect_timeout', 10);
        $this->retries = (int) config('z2.retries', 3);
        $this->retryDelay = config('z2.retry_delay', [1, 5, 10]);
        $this->sessionCacheKey = config('z2.session_cache_key', 'z2_jsessionid');
        $this->sessionCacheTtl = (int) config('z2.session_cache_ttl', 3600);

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => $this->timeout,
            'connect_timeout' => $this->connectTimeout,
            'http_errors' => false,
            'verify' => true,
        ]);
    }

    /**
     * Authenticate with the Z2 API using RSA encrypted credentials.
     *
     * @throws Z2AuthenticationException
     */
    public function login(string $username, string $password): array
    {
        $encryptedPassword = $this->encryptPassword($password);
        $credentials = [
            'username' => $username,
            'password' => $encryptedPassword,
            'lang' => 'en',
            'area' => 'America',
            'systemFlag' => '0',
            'appid' => '1',
            'appversion' => '200',
            'phone' => 'web',
            'timezone' => -5,
        ];

        Log::info('Z2 login attempt', ['username' => $username]);

        try {
            $response = $this->client->post('/User/loginR', [
                'json' => $credentials,
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
            ]);

            $body = (string) $response->getBody();
            $data = json_decode($body, true) ?? [];

            Log::info('Z2 login response', [
                'status' => $response->getStatusCode(),
                'body' => $data,
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new Z2AuthenticationException(
                    'Login failed with status: ' . $response->getStatusCode(),
                    $response->getStatusCode(),
                    $body,
                    '/User/loginR'
                );
            }

            if (isset($data['code']) && $data['code'] !== 0) {
                throw new Z2AuthenticationException(
                    $data['msg'] ?? 'Login failed',
                    $response->getStatusCode(),
                    $body,
                    '/User/loginR'
                );
            }

            // Extract and store JSESSIONID
            $sessionId = $this->extractSessionId($response->getHeaders());
            if ($sessionId) {
                $this->storeSession($sessionId);
            }

            return $data;
        } catch (Z2AuthenticationException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->handleError($e);
            throw new Z2AuthenticationException(
                'Login request failed: ' . $e->getMessage(),
                null,
                null,
                '/User/loginR',
                $e
            );
        }
    }

    /**
     * Get the current JSESSIONID, initializing the session if necessary.
     */
    public function getSession(): string
    {
        $sessionId = Cache::get($this->sessionCacheKey);

        if (empty($sessionId)) {
            $this->login($this->username, $this->password);
            $sessionId = Cache::get($this->sessionCacheKey);
        }

        return (string) $sessionId;
    }

    /**
     * Refresh the current session by re-authenticating.
     *
     * @throws Z2AuthenticationException
     */
    public function refreshSession(): void
    {
        Cache::forget($this->sessionCacheKey);
        $this->login($this->username, $this->password);
    }

    /**
     * Make an HTTP request to the Z2 API with automatic retries and session handling.
     *
     * @throws Z2ApiException
     * @throws Z2AuthenticationException
     * @throws Z2SessionExpiredException
     */
    public function request(string $method, string $endpoint, array $data = []): array
    {
        $sessionId = $this->getSession();
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');
        $lastException = null;

        $attempts = min($this->retries, count($this->retryDelay));

        for ($attempt = 0; $attempt <= $attempts; $attempt++) {
            Log::info('Z2 API request', [
                'method' => $method,
                'endpoint' => $endpoint,
                'attempt' => $attempt + 1,
                'data' => $data,
            ]);

            try {
                $options = [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                        'Cookie' => 'JSESSIONID=' . $sessionId,
                    ],
                ];

                if (strtoupper($method) === 'GET') {
                    $options['query'] = $data;
                } else {
                    $options['json'] = $data;
                }

                $response = $this->client->request($method, $url, $options);
                $body = (string) $response->getBody();
                $data = json_decode($body, true) ?? [];

                Log::info('Z2 API response', [
                    'endpoint' => $endpoint,
                    'status' => $response->getStatusCode(),
                    'body' => $data,
                ]);

                if ($response->getStatusCode() === 401) {
                    throw new Z2SessionExpiredException(
                        'Session expired',
                        401,
                        $body,
                        $endpoint
                    );
                }

                if ($response->getStatusCode() >= 400) {
                    throw new Z2ApiException(
                        'API error: ' . $response->getStatusCode(),
                        $response->getStatusCode(),
                        $body,
                        $endpoint
                    );
                }

                if (isset($data['code']) && $data['code'] !== 0) {
                    throw new Z2ApiException(
                        $data['msg'] ?? 'API error',
                        $response->getStatusCode(),
                        $body,
                        $endpoint
                    );
                }

                return $data;
            } catch (Z2SessionExpiredException $e) {
                Log::warning('Z2 session expired, attempting refresh', ['endpoint' => $endpoint]);
                $this->refreshSession();
                $sessionId = $this->getSession();
                $lastException = $e;
                // Do not count session refresh as a retry attempt
                continue;
            } catch (Z2ApiException $e) {
                $lastException = $e;
                break; // Do not retry application errors
            } catch (ConnectException $e) {
                $lastException = $e;
                $delay = $this->retryDelay[$attempt] ?? 10;
                Log::warning('Z2 connection error, retrying', [
                    'endpoint' => $endpoint,
                    'delay' => $delay,
                    'attempt' => $attempt + 1,
                ]);
                if ($attempt < $attempts) {
                    sleep($delay);
                }
            } catch (RequestException $e) {
                $lastException = $e;
                $delay = $this->retryDelay[$attempt] ?? 10;
                Log::warning('Z2 request error, retrying', [
                    'endpoint' => $endpoint,
                    'delay' => $delay,
                    'attempt' => $attempt + 1,
                    'error' => $e->getMessage(),
                ]);
                if ($attempt < $attempts) {
                    sleep($delay);
                }
            }
        }

        $this->handleError($lastException ?? new Z2ApiException('Request failed after retries'));
        throw new Z2ApiException(
            'Request failed after ' . ($attempts + 1) . ' attempts: ' . ($lastException?->getMessage() ?? 'Unknown error'),
            null,
            null,
            $endpoint,
            $lastException ?? null
        );
    }

    /**
     * Handle and log errors from the Z2 API.
     *
     * @throws Z2ApiException
     * @throws Z2AuthenticationException
     * @throws Z2SessionExpiredException
     */
    public function handleError(Throwable $e): void
    {
        if ($e instanceof Z2ApiException) {
            Log::error('Z2 API error', [
                'message' => $e->getMessage(),
                'endpoint' => $e->endpoint,
                'status_code' => $e->statusCode,
                'response_body' => $e->responseBody,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }

        if ($e instanceof Z2AuthenticationException) {
            Log::error('Z2 authentication error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }

        if ($e instanceof Z2SessionExpiredException) {
            Log::error('Z2 session expired', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }

        Log::error('Z2 unexpected error', [
            'message' => $e->getMessage(),
            'class' => get_class($e),
            'trace' => $e->getTraceAsString(),
        ]);

        throw new Z2ApiException(
            'Unexpected error: ' . $e->getMessage(),
            null,
            null,
            '',
            $e
        );
    }

    /**
     * Encrypt a password using the configured RSA public key.
     */
    protected function encryptPassword(string $password): string
    {
        $publicKey = $this->rsaPublicKey;

        if (empty($publicKey)) {
            return $password;
        }

        // Ensure the key is properly formatted
        if (!str_contains($publicKey, 'BEGIN PUBLIC KEY')) {
            $publicKey = "-----BEGIN PUBLIC KEY-----\n" . chunk_split($publicKey, 64) . "-----END PUBLIC KEY-----";
        }

        $encrypted = '';
        openssl_public_encrypt($password, $encrypted, $publicKey);

        return base64_encode($encrypted);
    }

    /**
     * Extract JSESSIONID from response headers.
     */
    protected function extractSessionId(array $headers): ?string
    {
        $cookies = $headers['Set-Cookie'] ?? [];

        foreach ((array) $cookies as $cookie) {
            if (str_contains((string) $cookie, 'JSESSIONID=')) {
                preg_match('/JSESSIONID=([^;]+)/', (string) $cookie, $matches);
                return $matches[1] ?? null;
            }
        }

        return null;
    }

    /**
     * Store the JSESSIONID in cache.
     */
    protected function storeSession(string $sessionId): void
    {
        Cache::put($this->sessionCacheKey, $sessionId, $this->sessionCacheTtl);
        Log::info('Z2 session stored', ['cache_key' => $this->sessionCacheKey]);
    }
}
