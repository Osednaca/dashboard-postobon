<?php

namespace App\Services\External\Z2;

use App\Exceptions\Z2ApiException;

/**
 * Z2 Device service endpoints.
 */
class DeviceService
{
    public function __construct(
        protected FanCloudService $fanCloudService,
    ) {}

    /**
     * Get group device list (POST /User/groupDeviceList).
     *
     * @param array<string, mixed> $filters
     *
     * @throws Z2ApiException
     */
    public function groupDeviceList(array $filters): array
    {
        return $this->fanCloudService->request('POST', '/User/groupDeviceList', $filters);
    }

    /**
     * Control device power (POST /User/devicePower).
     *
     * @param array<string, mixed> $params
     *
     * @throws Z2ApiException
     */
    public function devicePower(array $params): array
    {
        return $this->fanCloudService->request('POST', '/User/devicePower', $params);
    }

    /**
     * Unbind a device (POST /User/unbindDevice).
     *
     * @param array<string, mixed> $params
     *
     * @throws Z2ApiException
     */
    public function unbindDevice(array $params): array
    {
        return $this->fanCloudService->request('POST', '/User/unbindDevice', $params);
    }

    /**
     * Get UI list (POST /Effect/getUiListIsVersion).
     *
     * @param array<string, mixed> $params
     *
     * @throws Z2ApiException
     */
    public function getUiList(array $params): array
    {
        return $this->fanCloudService->request('POST', '/Effect/getUiListIsVersion', $params);
    }

    /**
     * Upgrade device UI (POST /User/upgradeDeviceUi).
     *
     * @param array<string, mixed> $params
     *
     * @throws Z2ApiException
     */
    public function upgradeDeviceUi(array $params): array
    {
        return $this->fanCloudService->request('POST', '/User/upgradeDeviceUi', $params);
    }

    /**
     * Update device group (POST /User/updateDeviceGroup).
     *
     * @param array<string, mixed> $params
     *
     * @throws Z2ApiException
     */
    public function updateDeviceGroup(array $params): array
    {
        return $this->fanCloudService->request('POST', '/User/updateDeviceGroup', $params);
    }
}
