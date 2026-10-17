<?php

namespace App\Repositories\Eloquent;

use App\Data\City;
use App\Data\SensorData;
use App\Models\Sensor;
use App\Repositories\Contracts\SensorRepositoryInterface;

final class EloquentSensorRepository implements SensorRepositoryInterface
{
    /**
     * @param  SensorData  $sensor
     * @param  City  $city
     * @param  float  $distanceKm
     * @return void
     */
    public function save(SensorData $sensor, City $city, float $distanceKm): void
    {
        Sensor::query()->updateOrCreate(
            ['id' => $sensor->id],
            [
                'type' => $sensor->type,
                'city' => $city->name,
                'latitude' => $sensor->latitude,
                'longitude' => $sensor->longitude,
                'distance_km' => round($distanceKm, 3),
            ],
        );
    }

    /**
     * @return array<int, string>
     */
    public function citiesBySensorId(): array
    {
        return Sensor::query()->pluck('city', 'id')->all();
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return Sensor::query()->count();
    }
}
