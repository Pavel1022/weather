<?php

namespace App\Services\AirQuality\Calculation;

use App\Data\CityAverage;
use App\Data\CitySummary;

final class CityAverageCalculator
{
    /**
     * @var int
     */
    private const PRECISION = 2;

    /**
     * @param  CitySummary  $summary
     * @return CityAverage
     */
    public function calculate(CitySummary $summary): CityAverage
    {
        if ($summary->sensorDays === 0) {
            return $this->empty($summary->city);
        }

        return new CityAverage(
            $summary->city,
            round($summary->sumOfDailyAverages / $summary->sensorDays, self::PRECISION),
            $summary->sensors,
            $summary->measurements,
        );
    }

    /**
     * @param  string  $city
     * @return CityAverage
     */
    public function empty(string $city): CityAverage
    {
        return new CityAverage($city, null, 0, 0);
    }
}
