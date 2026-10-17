<?php

namespace App\Services\AirQuality\Contracts;

use App\Data\DateRange;
use App\Data\Ranking;

interface CleanAirRankingInterface
{
    /**
     * @param  DateRange  $period
     * @return Ranking
     */
    public function rank(DateRange $period): Ranking;
}
