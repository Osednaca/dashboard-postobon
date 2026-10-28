<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CampaignDeviceFile extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['owned' => 'boolean', 'evidence' => 'array', 'requested_at' => 'datetime', 'confirmed_at' => 'datetime', 'video_index' => 'integer'];
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class)->withTrashed();
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(CampaignDelivery::class);
    }
}
