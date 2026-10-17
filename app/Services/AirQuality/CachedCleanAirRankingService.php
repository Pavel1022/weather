<?php

namespace App\Services\AirQuality;

use App\Data\CityAverage;
use App\Data\DateRange;
use App\Data\Ranking;
use App\Services\AirQuality\Contracts\CleanAirRankingInterface;
use Illuminate\Contracts\Cache\Repository;

final class CachedCleanAirRankingService implements CleanAirRankingInterface
{
    /**
     * @var string
     */
    private const VERSION_KEY = 'ranking:version';

    /**
     * @param  CleanAirRankingInterface  $inner
     * @param  Repository  $cache
     * @param  int  $ttlSeconds
     */
    public function __construct(
        private readonly CleanAirRankingInterface $inner,
        private readonly Repository $cache,
        private readonly int $ttlSeconds,
    ) {}

    /**
     * @param  DateRange  $period
     * @return Ranking
     */
    public function rank(DateRange $period): Ranking
    {
        $cities = $this->cache->remember(
            $this->key($period),
            $this->ttlSeconds,
            fn (): array => $this->toArray($this->inner->rank($period)),
        );

        return new Ranking($period, array_map(
            fn (array $city): CityAverage => new CityAverage($city['city'], $city['avg'], $city['sensors'], $city['measurements']),
            $cities,
        ));
    }

    /**
     * @return void
     */
    public function flush(): void
    {
        $this->cache->forever(self::VERSION_KEY, $this->version() + 1);
    }

    /**
     * @param  DateRange  $period
     * @return string
     */
    private function key(DateRange $period): string
    {
        return sprintf('ranking:%d:%s:%s', $this->version(), $period->from->toDateString(), $period->to->toDateString());
    }

    /**
     * @return int
     */
    private function version(): int
    {
        return (int) $this->cache->get(self::VERSION_KEY, 1);
    }

    /**
     * @param  Ranking  $ranking
     * @return list<array{city: string, avg: float|null, sensors: int, measurements: int}>
     */
    private function toArray(Ranking $ranking): array
    {
        return array_map(fn (CityAverage $city): array => [
            'city' => $city->city,
            'avg' => $city->averagePm25,
            'sensors' => $city->sensors,
            'measurements' => $city->measurements,
        ], $ranking->cities);
    }
}
