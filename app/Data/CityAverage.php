<?php

namespace App\Data;

final readonly class CityAverage
{
    /**
     * @param  string  $city
     * @param  float|null  $averagePm25
     * @param  int  $sensors
     * @param  int  $measurements
     */
    public function __construct(
        public string $city,
        public ?float $averagePm25,
        public int $sensors,
        public int $measurements,
    ) {}

    /**
     * @return bool
     */
    public function hasData(): bool
    {
        return $this->averagePm25 !== null;
    }
}
