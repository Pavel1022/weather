<?php

namespace App\Data;

use Carbon\CarbonImmutable;

final readonly class SensorDayAverage
{
    /**
     * @param  int  $sensorId
     * @param  string  $city
     * @param  CarbonImmutable  $date
     * @param  float  $sum
     * @param  int  $count
     * @param  float  $average
     */
    public function __construct(
        public int $sensorId,
        public string $city,
        public CarbonImmutable $date,
        public float $sum,
        public int $count,
        public float $average,
    ) {}
}
