<?php

namespace App\Services\Api\SensorCommunity\Contracts;

use App\Data\SensorData;
use App\Services\Api\SensorCommunity\Exceptions\SensorCommunityException;

interface SensorDirectoryInterface
{
    /**
     * @param  float  $latitude
     * @param  float  $longitude
     * @param  float  $radiusKm
     * @return list<SensorData>
     *
     * @throws SensorCommunityException
     */
    public function outdoorParticulateSensorsAround(float $latitude, float $longitude, float $radiusKm): array;
}
