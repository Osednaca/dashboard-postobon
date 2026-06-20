<?php

namespace App\Services\External\Z2;

use App\Exceptions\Z2ApiException;

/**
 * Z2 Group service endpoints.
 */
class GroupService
{
    public function __construct(
        protected FanCloudService $fanCloudService,
    ) {}

    /**
     * Add a new group (POST /User/addGroup).
     *
     * @param array<string, mixed> $params
     *
     * @throws Z2ApiException
     */
    public function addGroup(array $params): array
    {
        return $this->fanCloudService->request('POST', '/User/addGroup', $params);
    }

    /**
     * Update an existing group (POST /User/updateDeviceGroup).
     *
     * @param array<string, mixed> $params
     *
     * @throws Z2ApiException
     */
    public function updateGroup(array $params): array
    {
        return $this->fanCloudService->request('POST', '/User/updateDeviceGroup', $params);
    }
}
