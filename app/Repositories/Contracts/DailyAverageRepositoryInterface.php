<?php

namespace App\Repositories\Contracts;

use App\Data\CitySummary;
use App\Data\DateRange;
use App\Data\SensorDayAverage;

interface DailyAverageRepositoryInterface
{
    /**
     * @param  list<SensorDayAverage>  $averages
     * @return void
     */
    public function saveMany(array $averages): void;

    /**
     * @param  DateRange  $range
     * @return array<string, CitySummary>
     */
    public function summariesBetween(DateRange $range): array;

    /**
     * @return DateRange|null
     */
    public function availableRange(): ?DateRange;
}
