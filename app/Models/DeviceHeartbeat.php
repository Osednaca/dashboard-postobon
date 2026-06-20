<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceHeartbeat extends Model
{
    use HasFactory;

    protected $table = 'device_heartbeats';

    protected $fillable = [
        'device_id',
        'rpm',
        'status',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'rpm' => 'integer',
            'received_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
