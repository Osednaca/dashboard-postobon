<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignDelivery extends Model
{
    protected $guarded = [];

    public function file(): BelongsTo
    {
        return $this->belongsTo(CampaignDeviceFile::class, 'campaign_device_file_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class)->withTrashed();
    }

    public function getCleanupStatusAttribute(): string
    {
        $file = $this->file;
        if (in_array($file->state, ['ready', 'preserved']) && ($this->released_at || $this->campaign->trashed() || $this->campaign->status === 'finished')) {
            $shared = $file->deliveries->contains(fn ($delivery) => $delivery->campaign_id !== $this->campaign_id && ! $delivery->released_at && ! $delivery->campaign->trashed() && (in_array($delivery->campaign->status, ['active', 'paused']) || ($delivery->campaign->status === 'scheduled' && ! $delivery->campaign->start_date?->isFuture())) && (! $delivery->campaign->end_date || $delivery->campaign->end_date->isFuture()));
            if ($shared) {
                return 'shared';
            }
        }

        return $file->state;
    }
}
