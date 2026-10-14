<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Group extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'z2_group_id',
        'business_type_id',
        'address',
        'city',
        'latitude',
        'longitude',
        'google_place_id',
    ];

    protected function casts(): array
    {
        return ['latitude' => 'decimal:8', 'longitude' => 'decimal:8'];
    }

    public function businessType(): BelongsTo
    {
        return $this->belongsTo(BusinessType::class);
    }

    public function wl35Devices(): HasMany
    {
        return $this->hasMany(Wl35DeviceProfile::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }
}
