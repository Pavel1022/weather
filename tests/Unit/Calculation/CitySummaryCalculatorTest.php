<?php

namespace Tests\Unit\Calculation;

use App\Data\SensorDayAverage;
use App\Services\AirQuality\Calculation\CitySummaryCalculator;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

final class CitySummaryCalculatorTest extends TestCase
{
    /**
     * @return void
     */
    public function test_it_adds_up_sensor_days_per_city(): void
    {
        $summaries = (new CitySummaryCalculator)->summarize([
            $this->average(1, 'Sofia', '2026-10-01', 10.0, 500),
            $this->average(1, 'Sofia', '2026-10-02', 20.0, 400),
            $this->average(2, 'Sofia', '2026-10-01', 30.0, 100),
            $this->average(3, 'Varna', '2026-10-01', 8.0, 300),
        ]);

        $this->assertEqualsCanonicalizing(['Sofia', 'Varna'], array_keys($summaries));

        $sofia = $summaries['Sofia'];
        $this->assertSame(60.0, $sofia->sumOfDailyAverages);
        $this->assertSame(3, $sofia->sensorDays);
        $this->assertSame(2, $sofia->sensors);
        $this->assertSame(1000, $sofia->measurements);

        $this->assertSame(1, $summaries['Varna']->sensors);
        $this->assertSame(8.0, $summaries['Varna']->sumOfDailyAverages);
    }

    /**
     * @return void
     */
    public function test_nothing_in_gives_nothing_out(): void
    {
        $this->assertSame([], (new CitySummaryCalculator)->summarize([]));
    }

    /**
     * @param  int  $sensorId
     * @param  string  $city
     * @param  string  $date
     * @param  float  $average
     * @param  int  $count
     * @return SensorDayAverage
     */
    private function average(int $sensorId, string $city, string $date, float $average, int $count): SensorDayAverage
    {
        return new SensorDayAverage($sensorId, $city, CarbonImmutable::parse($date), $average * $count, $count, $average);
    }
}
