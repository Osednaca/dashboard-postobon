<?php

namespace App\Listeners;

use App\Events\CampaignActivated;
use App\Events\CampaignFinished;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;

class SendCampaignNotification
{
    public function handle(CampaignActivated|CampaignFinished $event): void
    {
        $campaign = $event->campaign;
        $type = $event instanceof CampaignActivated ? 'activated' : 'finished';
        $title = $event instanceof CampaignActivated ? 'Campaña activada' : 'Campaña finalizada';
        $message = $event instanceof CampaignActivated
            ? "La campaña {$campaign->name} ha sido activada."
            : "La campaña {$campaign->name} ha finalizado.";

        Notification::create([
            'user_id' => $campaign->created_by,
            'type' => 'campaign_' . $type,
            'title' => $title,
            'message' => $message,
            'data' => [
                'campaign_id' => $campaign->id,
                'campaign_name' => $campaign->name,
            ],
        ]);

        Log::info("Campaña {$campaign->name} {$type}");
    }
}
