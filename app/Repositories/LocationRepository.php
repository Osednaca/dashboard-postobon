<?php

namespace App\Repositories;

use App\Models\Location;
use App\Repositories\Contracts\LocationRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class LocationRepository extends BaseRepository implements LocationRepositoryInterface
{
    /**
     * LocationRepository constructor.
     *
     * @param Location $location
     */
    public function __construct(Location $location)
    {
        parent::__construct($location);
    }

    /**
     * @inheritDoc
     */
    public function findByCity(string $city): Collection
    {
        return $this->model->where('city', $city)->get();
    }

    /**
     * @inheritDoc
     */
    public function findByCoordinates(float $latitude, float $longitude): ?Location
    {
        /** @var Location|null */
        return $this->model
            ->where('latitude', $latitude)
            ->where('longitude', $longitude)
            ->first();
    }
}
