<?php

namespace App\Services\AirQuality\Calculation;

final class HaversineDistance
{
    /**
     * @var float
     */
    private const EARTH_RADIUS_KM = 6371.0;

    /**
     * @param  float  $latitudeA
     * @param  float  $longitudeA
     * @param  float  $latitudeB
     * @param  float  $longitudeB
     * @return float
     */
    public function kilometersBetween(float $latitudeA, float $longitudeA, float $latitudeB, float $longitudeB): float
    {
        $latitudeFrom = deg2rad($latitudeA);
        $latitudeTo = deg2rad($latitudeB);
        $latitudeDelta = $latitudeTo - $latitudeFrom;
        $longitudeDelta = deg2rad($longitudeB - $longitudeA);

        $a = sin($latitudeDelta / 2) ** 2
            + cos($latitudeFrom) * cos($latitudeTo) * sin($longitudeDelta / 2) ** 2;

        return 2 * self::EARTH_RADIUS_KM * asin(min(1.0, sqrt($a)));
    }
}
