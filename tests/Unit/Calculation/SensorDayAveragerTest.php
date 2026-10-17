<?php

namespace Tests\Unit\Calculation;

use App\Data\PmReading;
use App\Services\AirQuality\Calculation\PmReadingFilter;
use App\Services\AirQuality\Calculation\SensorDayAverager;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

final class SensorDayAveragerTest extends TestCase
{
    /**
     * @var SensorDayAverager
     */
    private SensorDayAverager $averager;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->averager = new SensorDayAverager(new PmReadingFilter(minimum: 0.0, maximum: 500.0), minimumReadings: 3);
    }

    /**
     * @return void
     */
    public function test_it_averages_valid_readings(): void
    {
        $average = $this->averager->average(28910, 'Sofia', $this->day(), $this->readings([10.0, 20.0, 30.0]));

        $this->assertNotNull($average);
        $this->assertSame(28910, $average->sensorId);
        $this->assertSame('Sofia', $average->city);
        $this->assertSame(60.0, $average->sum);
        $this->assertSame(3, $average->count);
        $this->assertSame(20.0, $average->average);
    }

    /**
     * @return void
     */
    public function test_invalid_readings_are_left_out_and_counted(): void
    {
        $average = $this->averager->average(1, 'Sofia', $this->day(), $this->readings([10.0, 20.0, 999.9, null, 30.0]));

        $this->assertSame(20.0, $average?->average);
        $this->assertSame(3, $average?->count);
        $this->assertSame(2, $this->averager->lastRejectedCount());
    }

    /**
     * @return void
     */
    public function test_too_few_valid_readings_give_no_average(): void
    {
        $this->assertNull($this->averager->average(1, 'Sofia', $this->day(), $this->readings([10.0, 20.0])));
    }

    /**
     * @return void
     */
    public function test_only_invalid_readings_give_no_average(): void
    {
        $this->assertNull($this->averager->average(1, 'Sofia', $this->day(), $this->readings([999.9, -1.0, null])));
        $this->assertSame(3, $this->averager->lastRejectedCount());
    }

    /**
     * @return void
     */
    public function test_no_readings_give_no_average(): void
    {
        $this->assertNull($this->averager->average(1, 'Sofia', $this->day(), []));
        $this->assertSame(0, $this->averager->lastRejectedCount());
    }

    /**
     * @return void
     */
    public function test_the_date_is_stored_without_time(): void
    {
        $average = $this->averager->average(1, 'Sofia', CarbonImmutable::parse('2026-10-15 13:45:00'), $this->readings([1.0, 2.0, 3.0]));

        $this->assertSame('2026-10-15 00:00:00', $average?->date->toDateTimeString());
    }

    /**
     * @return CarbonImmutable
     */
    private function day(): CarbonImmutable
    {
        return CarbonImmutable::parse('2026-10-15');
    }

    /**
     * @param  list<float|null>  $values
     * @return list<PmReading>
     */
    private function readings(array $values): array
    {
        return array_map(fn (?float $value): PmReading => new PmReading($this->day(), $value), $values);
    }
}
