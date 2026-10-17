<?php

namespace App\Data;

final readonly class CitySummary
{
    /**
     * @param  string  $city
     * @param  float  $sumOfDailyAverages
     * @param  int  $sensorDays
     * @param  int  $sensors
     * @param  int  $measurements
     */
    public function __construct(
        public string $city,
        public float $sumOfDailyAverages,
        public int $sensorDays,
        public int $sensors,
        public int $measurements,
    ) {}
}
