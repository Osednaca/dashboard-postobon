<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EstablishmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business_type_id' => $this->business_type_id,
            'business_type' => $this->businessType ? $this->businessType->only(['id', 'name']) : null,
            'name' => $this->name,
            'contact_name' => $this->contact_name,
            'contact_phone' => $this->contact_phone,
            'address' => $this->address,
            'latitude' => $this->latitude === null ? null : (float) $this->latitude,
            'longitude' => $this->longitude === null ? null : (float) $this->longitude,
            'wifi_ssid' => $this->wifi_ssid,
            'has_wifi_password' => filled($this->getRawOriginal('wifi_password')),
            'devices_count' => (int) $this->devices_count,
            'wl35_device_profiles_count' => (int) $this->wl35_device_profiles_count,
        ];
    }
}
