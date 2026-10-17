<?php

namespace App\Services\AirQuality;

use App\Data\CityAverage;
use App\Data\DateRange;
use App\Data\Ranking;
use App\Repositories\Contracts\DailyAverageRepositoryInterface;
use App\Services\AirQuality\Calculation\CityAverageCalculator;
use App\Services\AirQuality\Calculation\CityRanker;
use App\Services\AirQuality\Calculation\CitySummaryCalculator;
use App\Services\AirQuality\Calculation\OutlierFilter;

final class CleanAirRankingService
{
    /**
     * @param  DailyAverageRepositoryInterface  $averages
     * @param  OutlierFilter  $outliers
     * @param  CitySummaryCalculator  $summaries
     * @param  CityCatalog  $cities
     * @param  CityAverageCalculator  $calculator
     * @param  CityRanker  $ranker
     */
    public function __construct(
        private readonly DailyAverageRepositoryInterface $averages,
        private readonly OutlierFilter $outliers,
        private readonly CitySummaryCalculator $summaries,
        private readonly CityCatalog $cities,
        private readonly CityAverageCalculator $calculator,
        private readonly CityRanker $ranker,
    ) {}

    /**
     * @param  DateRange  $period
     * @return Ranking
     */
    public function rank(DateRange $period): Ranking
    {
        $days = $this->averages->sensorDaysBetween($period);
        $summaries = $this->summaries->summarize($this->outliers->withoutOutliers($days));

        $averages = array_map(
            fn (string $city): CityAverage => isset($summaries[$city])
                ? $this->calculator->calculate($summaries[$city])
                : $this->calculator->empty($city),
            $this->cities->names(),
        );

        return $this->ranker->rank($period, $averages);
    }
}
