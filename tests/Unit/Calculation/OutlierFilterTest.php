<?php

namespace Tests\Unit\Calculation;

use App\Data\SensorDayAverage;
use App\Services\AirQuality\Calculation\OutlierFilter;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OutlierFilterTest extends TestCase
{
    /**
     * @var OutlierFilter
     */
    private OutlierFilter $filter;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->filter = new OutlierFilter(maxRatioToMedian: 5.0, minDifference: 25.0, minSensors: 3);
    }

    /**
     * @return void
     */
    public function test_a_broken_sensor_far_above_the_city_median_is_removed(): void
    {
        $kept = $this->filter->withoutOutliers([
            $this->average(1, 'Burgas', '2026-10-15', 2.0),
            $this->average(2, 'Burgas', '2026-10-15', 2.5),
            $this->average(3, 'Burgas', '2026-10-15', 3.0),
            $this->average(4, 'Burgas', '2026-10-15', 246.6),
        ]);

        $this->assertSame([1, 2, 3], $this->sensorIds($kept));
    }

    /**
     * @return void
     */
    public function test_a_high_but_believable_value_is_kept(): void
    {
        $kept = $this->filter->withoutOutliers([
            $this->average(1, 'Varna', '2026-10-15', 3.6),
            $this->average(2, 'Varna', '2026-10-15', 4.0),
            $this->average(3, 'Varna', '2026-10-15', 17.0),
        ]);

        $this->assertSame([1, 2, 3], $this->sensorIds($kept));
    }

    /**
     * @return void
     */
    public function test_heavy_smog_measured_by_every_sensor_is_kept(): void
    {
        $kept = $this->filter->withoutOutliers([
            $this->average(1, 'Plovdiv', '2026-01-10', 60.0),
            $this->average(2, 'Plovdiv', '2026-01-10', 85.0),
            $this->average(3, 'Plovdiv', '2026-01-10', 140.0),
            $this->average(4, 'Plovdiv', '2026-01-10', 210.0),
        ]);

        $this->assertSame([1, 2, 3, 4], $this->sensorIds($kept));
    }

    /**
     * @return void
     */
    public function test_cities_with_too_few_sensors_are_not_filtered(): void
    {
        $kept = $this->filter->withoutOutliers([
            $this->average(1, 'Ruse', '2026-10-15', 2.0),
            $this->average(2, 'Ruse', '2026-10-15', 300.0),
        ]);

        $this->assertSame([1, 2], $this->sensorIds($kept));
    }

    /**
     * @return void
     */
    public function test_each_city_and_day_has_its_own_median(): void
    {
        $kept = $this->filter->withoutOutliers([
            $this->average(1, 'Sofia', '2026-10-14', 4.0),
            $this->average(2, 'Sofia', '2026-10-14', 5.0),
            $this->average(3, 'Sofia', '2026-10-14', 60.0),
            $this->average(1, 'Sofia', '2026-10-15', 50.0),
            $this->average(2, 'Sofia', '2026-10-15', 55.0),
            $this->average(3, 'Sofia', '2026-10-15', 60.0),
            $this->average(4, 'Varna', '2026-10-14', 50.0),
            $this->average(5, 'Varna', '2026-10-14', 55.0),
            $this->average(6, 'Varna', '2026-10-14', 60.0),
        ]);

        $this->assertSame([1, 2, 1, 2, 3, 4, 5, 6], $this->sensorIds($kept));
    }

    /**
     * @return void
     */
    public function test_an_empty_list_stays_empty(): void
    {
        $this->assertSame([], $this->filter->withoutOutliers([]));
    }

    /**
     * @param  list<float>  $values
     * @param  float  $expected
     * @return void
     */
    #[DataProvider('medians')]
    public function test_it_calculates_the_median(array $values, float $expected): void
    {
        $this->assertSame($expected, $this->filter->median($values));
    }

    /**
     * @return array<string, array{list<float>, float}>
     */
    public static function medians(): array
    {
        return [
            'odd count' => [[9.0, 1.0, 5.0], 5.0],
            'even count' => [[4.0, 1.0, 3.0, 2.0], 2.5],
            'single value' => [[7.0], 7.0],
            'empty' => [[], 0.0],
        ];
    }

    /**
     * @param  float  $value
     * @param  bool  $expected
     * @return void
     */
    #[DataProvider('outliers')]
    public function test_both_the_ratio_and_the_difference_must_be_exceeded(float $value, bool $expected): void
    {
        $this->assertSame($expected, $this->filter->isOutlier($value, median: 4.0));
    }

    /**
     * @return array<string, array{float, bool}>
     */
    public static function outliers(): array
    {
        return [
            'normal' => [6.0, false],
            'five times but only 16 above' => [20.1, false],
            'eight times and thirty above' => [34.0, true],
            'far above' => [350.0, true],
        ];
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

    /**
     * @param  list<SensorDayAverage>  $averages
     * @return list<int>
     */
    private function sensorIds(array $averages): array
    {
        return array_map(fn (SensorDayAverage $average): int => $average->sensorId, $averages);
    }
}
