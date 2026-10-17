<?php

namespace App\Services\AirQuality\Calculation;

use App\Data\City;

final class CityResolver
{
    /**
     * @param  HaversineDistance  $distance
     * @param  float  $radiusKm
     */
    public function __construct(
        private readonly HaversineDistance $distance,
        private readonly float $radiusKm,
    ) {}

    /**
     * @param  float  $latitude
     * @param  float  $longitude
     * @param  list<City>  $cities
     * @return City|null
     */
    public function resolve(float $latitude, float $longitude, array $cities): ?City
    {
        $closest = null;
        $closestDistance = INF;

        foreach ($cities as $city) {
            $distance = $this->distanceTo($city, $latitude, $longitude);

            if ($distance <= $this->radiusKm && $distance < $closestDistance) {
                $closest = $city;
                $closestDistance = $distance;
            }
        }

        return $closest;
    }

    /**
     * @param  City  $city
     * @param  float  $latitude
     * @param  float  $longitude
     * @return float
     */
    public function distanceTo(City $city, float $latitude, float $longitude): float
    {
        return $this->distance->kilometersBetween($city->latitude, $city->longitude, $latitude, $longitude);
    }
}
