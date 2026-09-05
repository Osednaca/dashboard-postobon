<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Establishment extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_type_id',
        'name',
        'contact_name',
        'contact_phone',
        'address',
        'latitude',
        'longitude',
        'wifi_ssid',
        'wifi_password',
    ];

    protected $hidden = ['wifi_password'];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'wifi_password' => 'encrypted',
        ];
    }

    public function businessType(): BelongsTo
    {
        return $this->belongsTo(BusinessType::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function wl35DeviceProfiles(): HasMany
    {
        return $this->hasMany(Wl35DeviceProfile::class);
    }
}
