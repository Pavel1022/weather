<?php

namespace Tests\Feature\AirQuality;

use App\Data\DateRange;
use App\Data\ImportResult;
use App\Data\SensorDayAverage;
use App\Events\DayImported;
use App\Listeners\FlushRankingCache;
use App\Models\Sensor;
use App\Repositories\Contracts\DailyAverageRepositoryInterface;
use App\Services\AirQuality\CachedCleanAirRankingService;
use App\Services\AirQuality\Contracts\CleanAirRankingInterface;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class RankingCacheTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return void
     */
    public function test_the_ranking_interface_resolves_to_the_cached_service(): void
    {
        $this->assertInstanceOf(CachedCleanAirRankingService::class, $this->app->make(CleanAirRankingInterface::class));
    }

    /**
     * @return void
     */
    public function test_the_flush_listener_is_registered_for_imported_days(): void
    {
        Event::fake();

        Event::assertListening(DayImported::class, FlushRankingCache::class);
    }

    /**
     * @return void
     */
    public function test_new_data_is_visible_after_a_day_is_imported(): void
    {
        Sensor::query()->create(['id' => 1, 'type' => 'SDS011', 'city' => 'Sofia', 'latitude' => 42.0, 'longitude' => 23.0, 'distance_km' => 1.0]);
        $averages = $this->app->make(DailyAverageRepositoryInterface::class);
        $rankings = $this->app->make(CleanAirRankingInterface::class);
        $period = DateRange::fromStrings('2026-10-01', '2026-10-02');

        $averages->saveMany([new SensorDayAverage(1, 'Sofia', CarbonImmutable::parse('2026-10-01'), 1000.0, 100, 10.0)]);
        $this->assertSame(10.0, $rankings->rank($period)->cleanest()?->averagePm25);

        $averages->saveMany([new SensorDayAverage(1, 'Sofia', CarbonImmutable::parse('2026-10-02'), 3000.0, 100, 30.0)]);
        $this->assertSame(10.0, $rankings->rank($period)->cleanest()?->averagePm25);

        DayImported::dispatch(new ImportResult(CarbonImmutable::parse('2026-10-02'), 1, 0, 100, 0, 1, false));

        $this->assertSame(20.0, $rankings->rank($period)->cleanest()?->averagePm25);
    }
}
