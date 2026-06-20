<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignStatistic extends Model
{
    use HasFactory;

    protected $table = 'campaign_statistics';

    protected $fillable = [
        'campaign_id',
        'device_id',
        'impressions',
        'plays',
        'duration',
        'date',
    ];

    protected function casts(): array
    {
        return [
            'impressions' => 'integer',
            'plays' => 'integer',
            'duration' => 'float',
            'date' => 'date',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
