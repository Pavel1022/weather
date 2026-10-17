<?php

namespace App\Listeners;

use App\Events\DayImported;
use App\Services\AirQuality\CachedCleanAirRankingService;

final class FlushRankingCache
{
    /**
     * @param  CachedCleanAirRankingService  $rankings
     */
    public function __construct(private readonly CachedCleanAirRankingService $rankings) {}

    /**
     * @param  DayImported  $event
     * @return void
     */
    public function handle(DayImported $event): void
    {
        $this->rankings->flush();
    }
}
