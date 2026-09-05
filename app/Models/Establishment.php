<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

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
        ];
    }

    protected function wifiPassword(): Attribute
    {
        return Attribute::make(
            get: function (?string $value): ?string {
                if (blank($value)) {
                    return null;
                }

                try {
                    return Crypt::decryptString($value);
                } catch (DecryptException) {
                    return null;
                }
            },
            set: fn (?string $value): ?string => filled($value)
                ? Crypt::encryptString($value)
                : null,
        );
    }

    public function hasUnreadableWifiPassword(): bool
    {
        $encryptedPassword = $this->getRawOriginal('wifi_password');

        if (blank($encryptedPassword)) {
            return false;
        }

        try {
            Crypt::decryptString($encryptedPassword);

            return false;
        } catch (DecryptException) {
            return true;
        }
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
