<?php

namespace App\Jobs;

use App\Services\Campaigns\CampaignLifecycle;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessCampaignDeviceJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1900;

    public int $uniqueFor = 2000;

    public function __construct(public string $target)
    {
        $this->onQueue('campaigns');
    }

    public function uniqueId(): string
    {
        return $this->target;
    }

    public function handle(CampaignLifecycle $lifecycle): void
    {
        $lifecycle->processDevice($this->target);
    }
}
