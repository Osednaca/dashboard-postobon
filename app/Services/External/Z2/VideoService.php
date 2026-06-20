<?php

namespace App\Services\External\Z2;

use App\Exceptions\Z2ApiException;

/**
 * Z2 Video service endpoints.
 */
class VideoService
{
    public function __construct(
        protected FanCloudService $fanCloudService,
    ) {}

    /**
     * Upload a media file (POST /User/uploadMediaFile).
     *
     * @param array<string, mixed> $params
     *
     * @throws Z2ApiException
     */
    public function uploadMediaFile(array $params): array
    {
        return $this->fanCloudService->request('POST', '/User/uploadMediaFile', $params);
    }

    /**
     * Mark media upload as successful (POST /User/uploadMediaSuccessIsVersion).
     *
     * @param array<string, mixed> $params
     *
     * @throws Z2ApiException
     */
    public function uploadMediaSuccess(array $params): array
    {
        return $this->fanCloudService->request('POST', '/User/uploadMediaSuccessIsVersion', $params);
    }
}
