<?php

namespace App\Services\AirQuality;

use App\Repositories\Contracts\SensorRepositoryInterface;
use App\Services\AirQuality\Calculation\CityResolver;
use App\Services\Api\SensorCommunity\Contracts\SensorDirectoryInterface;
use App\Services\Api\SensorCommunity\Exceptions\SensorCommunityException;
use Illuminate\Support\Facades\Log;

final class SensorDiscoveryService
{
    /**
     * @param  SensorDirectoryInterface  $directory
     * @param  SensorRepositoryInterface  $sensors
     * @param  CityCatalog  $cities
     * @param  CityResolver  $resolver
     * @param  float  $radiusKm
     */
    public function __construct(
        private readonly SensorDirectoryInterface $directory,
        private readonly SensorRepositoryInterface $sensors,
        private readonly CityCatalog $cities,
        private readonly CityResolver $resolver,
        private readonly float $radiusKm,
    ) {}

    /**
     * @return array<string, int>
     *
     * @throws SensorCommunityException
     */
    public function discover(): array
    {
        $cities = $this->cities->all();
        $counts = array_fill_keys($this->cities->names(), 0);
        $saved = [];

        foreach ($cities as $city) {
            $found = $this->directory->outdoorParticulateSensorsAround($city->latitude, $city->longitude, $this->radiusKm);

            foreach ($found as $sensor) {
                if (isset($saved[$sensor->id])) {
                    continue;
                }

                $resolved = $this->resolver->resolve($sensor->latitude, $sensor->longitude, $cities);

                if ($resolved === null) {
                    continue;
                }

                $this->sensors->save(
                    $sensor,
                    $resolved,
                    $this->resolver->distanceTo($resolved, $sensor->latitude, $sensor->longitude),
                );

                $saved[$sensor->id] = true;
                $counts[$resolved->name]++;
            }
        }

        Log::channel('import')->info('Sensors discovered', $counts);

        return $counts;
    }
}
