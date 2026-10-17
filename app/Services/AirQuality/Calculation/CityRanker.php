<?php

namespace App\Services\AirQuality\Calculation;

use App\Data\CityAverage;
use App\Data\DateRange;
use App\Data\Ranking;

final class CityRanker
{
    /**
     * @param  DateRange  $period
     * @param  list<CityAverage>  $averages
     * @return Ranking
     */
    public function rank(DateRange $period, array $averages): Ranking
    {
        $withData = array_values(array_filter($averages, fn (CityAverage $average): bool => $average->hasData()));
        $withoutData = array_values(array_filter($averages, fn (CityAverage $average): bool => ! $average->hasData()));

        usort($withData, fn (CityAverage $a, CityAverage $b): int => [$a->averagePm25, $a->city] <=> [$b->averagePm25, $b->city]);
        usort($withoutData, fn (CityAverage $a, CityAverage $b): int => $a->city <=> $b->city);

        return new Ranking($period, [...$withData, ...$withoutData]);
    }
}
