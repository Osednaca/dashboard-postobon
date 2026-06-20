<?php

namespace App\Repositories;

use App\Models\CampaignStatistic;
use App\Repositories\Contracts\CampaignStatisticRepositoryInterface;

class CampaignStatisticRepository extends BaseRepository implements CampaignStatisticRepositoryInterface
{
    /**
     * CampaignStatisticRepository constructor.
     *
     * @param CampaignStatistic $campaignStatistic
     */
    public function __construct(CampaignStatistic $campaignStatistic)
    {
        parent::__construct($campaignStatistic);
    }
}
