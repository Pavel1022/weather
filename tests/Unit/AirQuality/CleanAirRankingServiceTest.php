<?php

namespace Tests\Unit\AirQuality;

use App\Data\CityAverage;
use App\Data\DateRange;
use App\Data\SensorDayAverage;
use App\Repositories\Contracts\DailyAverageRepositoryInterface;
use App\Services\AirQuality\Calculation\CityAverageCalculator;
use App\Services\AirQuality\Calculation\CityRanker;
use App\Services\AirQuality\Calculation\CitySummaryCalculator;
use App\Services\AirQuality\Calculation\OutlierFilter;
use App\Services\AirQuality\CityCatalog;
use App\Services\AirQuality\CleanAirRankingService;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

final class CleanAirRankingServiceTest extends TestCase
{
    /**
     * @return void
     */
    public function test_it_ranks_every_city_and_puts_cities_without_data_last(): void
    {
        $ranking = $this->service([
            $this->day(1, 'Sofia', '2026-10-01', 6.0),
            $this->day(2, 'Sofia', '2026-10-01', 4.0),
            $this->day(3, 'Varna', '2026-10-01', 3.0),
        ])->rank(DateRange::fromStrings('2026-10-01', '2026-10-01'));

        $this->assertSame(['Varna', 'Sofia', 'Burgas', 'Plovdiv', 'Ruse'], $this->cityNames($ranking->cities));
        $this->assertSame(3.0, $ranking->cities[0]->averagePm25);
        $this->assertSame(5.0, $ranking->cities[1]->averagePm25);
        $this->assertSame(2, $ranking->cities[1]->sensors);
        $this->assertNull($ranking->cities[2]->averagePm25);
        $this->assertSame('Varna', $ranking->cleanest()?->city);
    }

    /**
     * @return void
     */
    public function test_a_faulty_sensor_does_not_change_the_ranking(): void
    {
        $ranking = $this->service([
            $this->day(1, 'Burgas', '2026-10-15', 2.0),
            $this->day(2, 'Burgas', '2026-10-15', 2.5),
            $this->day(3, 'Burgas', '2026-10-15', 3.0),
            $this->day(4, 'Burgas', '2026-10-15', 246.6),
            $this->day(5, 'Sofia', '2026-10-15', 5.0),
        ])->rank(DateRange::fromStrings('2026-10-15', '2026-10-15'));

        $this->assertSame('Burgas', $ranking->cleanest()?->city);
        $this->assertSame(2.5, $ranking->cleanest()?->averagePm25);
        $this->assertSame(3, $ranking->cleanest()?->sensors);
    }

    /**
     * @return void
     */
    public function test_a_period_without_data_has_no_cleanest_city(): void
    {
        $ranking = $this->service([])->rank(DateRange::fromStrings('2026-10-01', '2026-10-31'));

        $this->assertCount(5, $ranking->cities);
        $this->assertNull($ranking->cleanest());
        $this->assertSame('2026-10-31', $ranking->period->to->toDateString());
    }

    /**
     * @param  list<SensorDayAverage>  $days
     * @return CleanAirRankingService
     */
    private function service(array $days): CleanAirRankingService
    {
        $repository = new class($days) implements DailyAverageRepositoryInterface
        {
            /**
             * @param  list<SensorDayAverage>  $days
             */
            public function __construct(private readonly array $days) {}

            /**
             * @param  list<SensorDayAverage>  $averages
             * @return void
             */
            public function saveMany(array $averages): void {}

            /**
             * @param  DateRange  $range
             * @return list<SensorDayAverage>
             */
            public function sensorDaysBetween(DateRange $range): array
            {
                return $this->days;
            }

            /**
             * @return DateRange|null
             */
            public function availableRange(): ?DateRange
            {
                return null;
            }
        };

        return new CleanAirRankingService(
            $repository,
            new OutlierFilter(maxRatioToMedian: 5.0, minDifference: 25.0, minSensors: 3),
            new CitySummaryCalculator,
            new CityCatalog([
                ['name' => 'Sofia', 'latitude' => 42.6977, 'longitude' => 23.3219],
                ['name' => 'Plovdiv', 'latitude' => 42.1354, 'longitude' => 24.7453],
                ['name' => 'Varna', 'latitude' => 43.2141, 'longitude' => 27.9147],
                ['name' => 'Burgas', 'latitude' => 42.5048, 'longitude' => 27.4626],
                ['name' => 'Ruse', 'latitude' => 43.8356, 'longitude' => 25.9657],
            ]),
            new CityAverageCalculator,
            new CityRanker,
        );
    }

    /**
     * @param  int  $sensorId
     * @param  string  $city
     * @param  string  $date
     * @param  float  $average
     * @return SensorDayAverage
     */
    private function day(int $sensorId, string $city, string $date, float $average): SensorDayAverage
    {
        return new SensorDayAverage($sensorId, $city, CarbonImmutable::parse($date), $average * 100, 100, $average);
    }

    /**
     * @param  list<CityAverage>  $cities
     * @return list<string>
     */
    private function cityNames(array $cities): array
    {
        return array_map(fn (CityAverage $city): string => $city->city, $cities);
    }
}
