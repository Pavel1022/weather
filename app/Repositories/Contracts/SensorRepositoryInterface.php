<?php

namespace App\Repositories\Contracts;

use App\Data\City;
use App\Data\SensorData;

interface SensorRepositoryInterface
{
    /**
     * @param  SensorData  $sensor
     * @param  City  $city
     * @param  float  $distanceKm
     * @return void
     */
    public function save(SensorData $sensor, City $city, float $distanceKm): void;

    /**
     * @return array<int, string>
     */
    public function citiesBySensorId(): array;

    /**
     * @return int
     */
    public function count(): int;
}
