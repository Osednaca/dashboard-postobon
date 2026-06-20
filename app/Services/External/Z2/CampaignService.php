<?php

namespace App\Services\External\Z2;

use App\Exceptions\Z2ApiException;

/**
 * Z2 Campaign service endpoints.
 */
class CampaignService
{
    public function __construct(
        protected FanCloudService $fanCloudService,
    ) {}

    /**
     * Publish a campaign to the Z2 platform.
     *
     * @param array<string, mixed> $params
     *
     * @throws Z2ApiException
     */
    public function publishCampaign(array $params): array
    {
        return $this->fanCloudService->request('POST', '/User/publishCampaign', $params);
    }
}
