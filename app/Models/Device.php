<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Device extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'mac_address',
        'firmware',
        'hardware',
        'rpm',
        'status',
        'location_id',
        'group_id',
        'last_heartbeat_at',
        'working_hours',
        'power_status',
    ];

    protected function casts(): array
    {
        return [
            'last_heartbeat_at' => 'datetime',
            'working_hours' => 'float',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function deviceCampaigns(): HasMany
    {
        return $this->hasMany(DeviceCampaign::class);
    }

    public function heartbeats(): HasMany
    {
        return $this->hasMany(DeviceHeartbeat::class);
    }

    public function statistics(): HasMany
    {
        return $this->hasMany(CampaignStatistic::class);
    }
}
