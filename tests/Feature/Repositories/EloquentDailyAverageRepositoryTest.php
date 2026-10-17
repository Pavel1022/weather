<?php

namespace Tests\Feature\Repositories;

use App\Data\DateRange;
use App\Data\SensorDayAverage;
use App\Models\Sensor;
use App\Models\SensorDailyAverage;
use App\Repositories\Contracts\DailyAverageRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EloquentDailyAverageRepositoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var DailyAverageRepositoryInterface
     */
    private DailyAverageRepositoryInterface $repository;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->createSensor(1, 'Sofia');
        $this->createSensor(2, 'Sofia');
        $this->createSensor(3, 'Varna');

        $this->repository = $this->app->make(DailyAverageRepositoryInterface::class);
    }

    /**
     * @return void
     */
    public function test_it_saves_daily_averages(): void
    {
        $this->repository->saveMany([
            $this->average(1, 'Sofia', '2026-10-01', 20.0),
            $this->average(3, 'Varna', '2026-10-01', 8.0),
        ]);

        $this->assertDatabaseCount('sensor_daily_averages', 2);
        $this->assertDatabaseHas('sensor_daily_averages', [
            'sensor_id' => 3,
            'city' => 'Varna',
            'pm25_count' => 100,
            'pm25_avg' => 8.0,
        ]);
    }

    /**
     * @return void
     */
    public function test_saving_an_empty_list_does_nothing(): void
    {
        $this->repository->saveMany([]);

        $this->assertDatabaseCount('sensor_daily_averages', 0);
    }

    /**
     * @return void
     */
    public function test_saving_the_same_sensor_and_day_twice_keeps_one_row_with_the_latest_values(): void
    {
        $this->repository->saveMany([$this->average(1, 'Sofia', '2026-10-01', 20.0)]);
        $this->repository->saveMany([$this->average(1, 'Sofia', '2026-10-01', 10.0)]);

        $this->assertSame(1, SensorDailyAverage::query()->count());
        $this->assertSame(10.0, SensorDailyAverage::query()->value('pm25_avg'));
    }

    /**
     * @return void
     */
    public function test_sensor_days_only_include_days_inside_the_range(): void
    {
        $this->repository->saveMany([
            $this->average(1, 'Sofia', '2026-09-30', 99.0),
            $this->average(1, 'Sofia', '2026-10-01', 10.0),
            $this->average(1, 'Sofia', '2026-10-02', 30.0),
            $this->average(1, 'Sofia', '2026-10-03', 99.0),
        ]);

        $days = $this->repository->sensorDaysBetween(DateRange::fromStrings('2026-10-01', '2026-10-02'));

        $this->assertSame(
            ['2026-10-01', '2026-10-02'],
            array_map(fn (SensorDayAverage $average): string => $average->date->toDateString(), $days),
        );
        $this->assertSame([10.0, 30.0], array_map(fn (SensorDayAverage $average): float => $average->average, $days));
    }

    /**
     * @return void
     */
    public function test_sensor_days_keep_all_stored_values(): void
    {
        $this->repository->saveMany([
            new SensorDayAverage(3, 'Varna', CarbonImmutable::parse('2026-10-01'), 960.0, 120, 8.0),
        ]);

        $day = $this->repository->sensorDaysBetween(DateRange::fromStrings('2026-10-01', '2026-10-01'))[0];

        $this->assertSame(3, $day->sensorId);
        $this->assertSame('Varna', $day->city);
        $this->assertSame('2026-10-01 00:00:00', $day->date->toDateTimeString());
        $this->assertSame(960.0, $day->sum);
        $this->assertSame(120, $day->count);
        $this->assertSame(8.0, $day->average);
    }

    /**
     * @return void
     */
    public function test_sensor_days_are_empty_when_there_is_no_data_in_the_range(): void
    {
        $this->repository->saveMany([$this->average(1, 'Sofia', '2026-10-01', 10.0)]);

        $this->assertSame([], $this->repository->sensorDaysBetween(DateRange::fromStrings('2026-11-01', '2026-11-30')));
    }

    /**
     * @return void
     */
    public function test_available_range_is_null_when_nothing_is_imported(): void
    {
        $this->assertNull($this->repository->availableRange());
    }

    /**
     * @return void
     */
    public function test_available_range_spans_the_first_and_last_imported_day(): void
    {
        $this->repository->saveMany([
            $this->average(1, 'Sofia', '2026-10-02', 10.0),
            $this->average(3, 'Varna', '2026-10-05', 8.0),
            $this->average(1, 'Sofia', '2026-10-01', 12.0),
        ]);

        $range = $this->repository->availableRange();

        $this->assertNotNull($range);
        $this->assertSame('2026-10-01', $range->from->toDateString());
        $this->assertSame('2026-10-05', $range->to->toDateString());
    }

    /**
     * @param  int  $id
     * @param  string  $city
     * @return void
     */
    private function createSensor(int $id, string $city): void
    {
        Sensor::query()->create([
            'id' => $id,
            'type' => 'SDS011',
            'city' => $city,
            'latitude' => 42.69,
            'longitude' => 23.32,
            'distance_km' => 1.0,
        ]);
    }

    /**
     * @param  int  $sensorId
     * @param  string  $city
     * @param  string  $date
     * @param  float  $average
     * @return SensorDayAverage
     */
    private function average(int $sensorId, string $city, string $date, float $average): SensorDayAverage
    {
        return new SensorDayAverage($sensorId, $city, CarbonImmutable::parse($date), $average * 100, 100, $average);
    }
}
