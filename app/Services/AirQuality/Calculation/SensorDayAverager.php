<?php

namespace App\Services\AirQuality\Calculation;

use App\Data\PmReading;
use App\Data\SensorDayAverage;
use Carbon\CarbonImmutable;

final class SensorDayAverager
{
    /**
     * @var int
     */
    private int $lastRejectedCount = 0;

    /**
     * @param  PmReadingFilter  $filter
     * @param  int  $minimumReadings
     */
    public function __construct(
        private readonly PmReadingFilter $filter,
        private readonly int $minimumReadings,
    ) {}

    /**
     * @param  int  $sensorId
     * @param  string  $city
     * @param  CarbonImmutable  $date
     * @param  iterable<PmReading>  $readings
     * @return SensorDayAverage|null
     */
    public function average(int $sensorId, string $city, CarbonImmutable $date, iterable $readings): ?SensorDayAverage
    {
        $sum = 0.0;
        $count = 0;
        $rejected = 0;

        foreach ($readings as $reading) {
            if ($this->filter->isValid($reading->pm25)) {
                $sum += $reading->pm25;
                $count++;
            } else {
                $rejected++;
            }
        }

        $this->lastRejectedCount = $rejected;

        if ($count === 0 || $count < $this->minimumReadings) {
            return null;
        }

        return new SensorDayAverage($sensorId, $city, $date->startOfDay(), $sum, $count, $sum / $count);
    }

    /**
     * @return int
     */
    public function lastRejectedCount(): int
    {
        return $this->lastRejectedCount;
    }
}
