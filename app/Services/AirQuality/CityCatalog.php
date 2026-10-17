<?php

namespace App\Services\AirQuality;

use App\Data\City;

final class CityCatalog
{
    /**
     * @param  list<array{name: string, latitude: float, longitude: float}>  $cities
     */
    public function __construct(private readonly array $cities) {}

    /**
     * @return list<City>
     */
    public function all(): array
    {
        return array_map(
            fn (array $city): City => new City($city['name'], (float) $city['latitude'], (float) $city['longitude']),
            $this->cities,
        );
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_column($this->cities, 'name');
    }
}
