<?php

namespace App\DTOs;

use Illuminate\Http\Request;

class LocationDTO
{
    /**
     * @param string|null $name
     * @param string|null $address
     * @param string|null $city
     * @param string|null $country
     * @param float|null $latitude
     * @param float|null $longitude
     * @param string|null $contactName
     * @param string|null $phone
     */
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $address = null,
        public readonly ?string $city = null,
        public readonly ?string $country = null,
        public readonly ?float $latitude = null,
        public readonly ?float $longitude = null,
        public readonly ?string $contactName = null,
        public readonly ?string $phone = null,
    ) {
    }

    /**
     * Create a LocationDTO from a Request.
     *
     * @param Request $request
     * @return self
     */
    public static function fromRequest(Request $request): self
    {
        return new self(
            name: $request->input('name'),
            address: $request->input('address'),
            city: $request->input('city'),
            country: $request->input('country'),
            latitude: $request->input('latitude') !== null ? (float) $request->input('latitude') : null,
            longitude: $request->input('longitude') !== null ? (float) $request->input('longitude') : null,
            contactName: $request->input('contact_name'),
            phone: $request->input('phone'),
        );
    }

    /**
     * Convert the DTO to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'address' => $this->address,
            'city' => $this->city,
            'country' => $this->country,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'contact_name' => $this->contactName,
            'phone' => $this->phone,
        ], fn ($value) => $value !== null);
    }
}
