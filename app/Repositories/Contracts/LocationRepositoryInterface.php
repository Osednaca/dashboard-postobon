<?php

namespace App\Repositories\Contracts;

use App\Models\Location;

interface LocationRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Find locations by city.
     *
     * @param string $city
     * @return \Illuminate\Database\Eloquent\Collection<int, Location>
     */
    public function findByCity(string $city): \Illuminate\Database\Eloquent\Collection;

    /**
     * Find a location by coordinates.
     *
     * @param float $latitude
     * @param float $longitude
     * @return Location|null
     */
    public function findByCoordinates(float $latitude, float $longitude): ?Location;
}
