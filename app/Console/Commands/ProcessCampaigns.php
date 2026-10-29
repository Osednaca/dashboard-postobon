<?php

namespace App\Console\Commands;

use App\Services\Campaigns\CampaignLifecycle;
use Illuminate\Console\Command;

class ProcessCampaigns extends Command
{
    protected $signature = 'campaigns:process';

    protected $description = 'Procesar vigencia, distribución y retiro de campañas.';

    public function handle(CampaignLifecycle $lifecycle): int
    {
        $lifecycle->tick();

        return self::SUCCESS;
    }
}
