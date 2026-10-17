<?php

namespace App\Data;

final readonly class Ranking
{
    /**
     * @param  DateRange  $period
     * @param  list<CityAverage>  $cities
     */
    public function __construct(
        public DateRange $period,
        public array $cities,
    ) {}

    /**
     * @return CityAverage|null
     */
    public function cleanest(): ?CityAverage
    {
        foreach ($this->cities as $city) {
            if ($city->hasData()) {
                return $city;
            }
        }

        return null;
    }
}
