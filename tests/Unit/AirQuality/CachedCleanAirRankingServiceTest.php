<?php

namespace Tests\Unit\AirQuality;

use App\Data\CityAverage;
use App\Data\DateRange;
use App\Data\Ranking;
use App\Services\AirQuality\CachedCleanAirRankingService;
use App\Services\AirQuality\Contracts\CleanAirRankingInterface;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use PHPUnit\Framework\TestCase;

final class CachedCleanAirRankingServiceTest extends TestCase
{
    /**
     * @var CleanAirRankingInterface
     */
    private CleanAirRankingInterface $inner;

    /**
     * @var CachedCleanAirRankingService
     */
    private CachedCleanAirRankingService $cached;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->inner = new class implements CleanAirRankingInterface
        {
            /**
             * @var int
             */
            public int $calls = 0;

            /**
             * @param  DateRange  $period
             * @return Ranking
             */
            public function rank(DateRange $period): Ranking
            {
                $this->calls++;

                return new Ranking($period, [
                    new CityAverage('Burgas', 2.16 + $this->calls, 5, 8607),
                    new CityAverage('Ruse', null, 0, 0),
                ]);
            }
        };

        $this->cached = new CachedCleanAirRankingService($this->inner, new Repository(new ArrayStore), 3600);
    }

    /**
     * @return void
     */
    public function test_the_same_period_is_calculated_only_once(): void
    {
        $period = DateRange::fromStrings('2026-10-14', '2026-10-16');

        $first = $this->cached->rank($period);
        $second = $this->cached->rank($period);

        $this->assertSame(1, $this->inner->calls);
        $this->assertEquals($first, $second);
    }

    /**
     * @return void
     */
    public function test_a_cached_ranking_is_rebuilt_with_all_values(): void
    {
        $period = DateRange::fromStrings('2026-10-14', '2026-10-16');
        $this->cached->rank($period);

        $ranking = $this->cached->rank($period);

        $this->assertSame('2026-10-14', $ranking->period->from->toDateString());
        $this->assertSame('Burgas', $ranking->cleanest()?->city);
        $this->assertSame(3.16, $ranking->cities[0]->averagePm25);
        $this->assertSame(5, $ranking->cities[0]->sensors);
        $this->assertSame(8607, $ranking->cities[0]->measurements);
        $this->assertNull($ranking->cities[1]->averagePm25);
    }

    /**
     * @return void
     */
    public function test_different_periods_are_cached_separately(): void
    {
        $this->cached->rank(DateRange::fromStrings('2026-10-14', '2026-10-16'));
        $this->cached->rank(DateRange::fromStrings('2026-10-01', '2026-10-16'));

        $this->assertSame(2, $this->inner->calls);
    }

    /**
     * @return void
     */
    public function test_flushing_forces_a_new_calculation(): void
    {
        $period = DateRange::fromStrings('2026-10-14', '2026-10-16');

        $before = $this->cached->rank($period);
        $this->cached->flush();
        $after = $this->cached->rank($period);

        $this->assertSame(2, $this->inner->calls);
        $this->assertSame(3.16, $before->cities[0]->averagePm25);
        $this->assertSame(4.16, $after->cities[0]->averagePm25);
    }
}
