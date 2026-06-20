<?php

namespace App\Services\External\Z2;

use App\Exceptions\Z2ApiException;

/**
 * Z2 Authentication service endpoints.
 */
class AuthService
{
    public function __construct(
        protected FanCloudService $fanCloudService,
    ) {}

    /**
     * Admin login endpoint (GET /admin/AdminLoginR).
     *
     * @param array<string, mixed> $credentials
     *
     * @throws Z2ApiException
     */
    public function adminLoginR(array $credentials): array
    {
        return $this->fanCloudService->request('GET', '/admin/AdminLoginR', $credentials);
    }

    /**
     * User login endpoint (POST /User/loginR).
     *
     * @param array<string, mixed> $credentials
     *
     * @throws Z2ApiException
     */
    public function loginR(array $credentials): array
    {
        return $this->fanCloudService->request('POST', '/User/loginR', $credentials);
    }
}
