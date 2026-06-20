<?php

namespace App\Services;

use App\Repositories\Contracts\LocationRepositoryInterface;
use Illuminate\Database\Eloquent\Model;

class LocationService extends BaseService
{
    /**
     * LocationService constructor.
     *
     * @param LocationRepositoryInterface $locationRepository
     */
    public function __construct(LocationRepositoryInterface $locationRepository)
    {
        parent::__construct($locationRepository);
    }

    /**
     * Find locations by city.
     *
     * @param string $city
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\Location>
     */
    public function findByCity(string $city): \Illuminate\Database\Eloquent\Collection
    {
        return $this->repository->findByCity($city);
    }

    /**
     * Find a location by coordinates.
     *
     * @param float $latitude
     * @param float $longitude
     * @return \App\Models\Location|null
     */
    public function findByCoordinates(float $latitude, float $longitude): ?\App\Models\Location
    {
        return $this->repository->findByCoordinates($latitude, $longitude);
    }
}
