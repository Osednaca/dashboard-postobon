<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Campaign extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'status',
        'priority',
        'start_date',
        'end_date',
        'segment_cities',
        'segment_groups',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'priority' => 'integer',
            'segment_cities' => 'json',
            'segment_groups' => 'json',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function media(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'campaign_media')
            ->withPivot('order')
            ->withTimestamps();
    }

    public function campaignMedia(): HasMany
    {
        return $this->hasMany(CampaignMedia::class);
    }

    public function deviceCampaigns(): HasMany
    {
        return $this->hasMany(DeviceCampaign::class);
    }

    public function statistics(): HasMany
    {
        return $this->hasMany(CampaignStatistic::class);
    }
}
