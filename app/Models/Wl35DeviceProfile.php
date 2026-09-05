<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Wl35DeviceProfile extends Model
{
    protected $fillable = [
        'device_id',
        'name',
        'establishment',
        'contact_name',
        'contact_phone',
        'contact_email',
        'address',
        'city',
        'country',
        'latitude',
        'longitude',
        'location_id',
        'group_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
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
}
