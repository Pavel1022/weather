<?php

namespace Tests\Unit\Calculation;

use App\Data\CitySummary;
use App\Services\AirQuality\Calculation\CityAverageCalculator;
use PHPUnit\Framework\TestCase;

final class CityAverageCalculatorTest extends TestCase
{
    /**
     * @return void
     */
    public function test_it_divides_the_sum_of_daily_averages_by_the_sensor_days(): void
    {
        $average = (new CityAverageCalculator)->calculate(new CitySummary('Sofia', 30.0, 3, 2, 300));

        $this->assertSame('Sofia', $average->city);
        $this->assertSame(10.0, $average->averagePm25);
        $this->assertSame(2, $average->sensors);
        $this->assertSame(300, $average->measurements);
        $this->assertTrue($average->hasData());
    }

    /**
     * @return void
     */
    public function test_it_rounds_to_two_decimals(): void
    {
        $this->assertSame(3.33, (new CityAverageCalculator)->calculate(new CitySummary('Sofia', 10.0, 3, 1, 300))->averagePm25);
    }

    /**
     * @return void
     */
    public function test_every_sensor_day_has_the_same_weight(): void
    {
        $average = (new CityAverageCalculator)->calculate(new CitySummary('Sofia', 10.0 + 30.0, 2, 2, 500 + 100));

        $this->assertSame(20.0, $average->averagePm25);
    }

    /**
     * @return void
     */
    public function test_a_city_without_sensor_days_has_no_average(): void
    {
        $average = (new CityAverageCalculator)->calculate(new CitySummary('Ruse', 0.0, 0, 0, 0));

        $this->assertNull($average->averagePm25);
        $this->assertFalse($average->hasData());
    }

    /**
     * @return void
     */
    public function test_an_empty_city_has_no_data(): void
    {
        $average = (new CityAverageCalculator)->empty('Ruse');

        $this->assertSame('Ruse', $average->city);
        $this->assertNull($average->averagePm25);
        $this->assertSame(0, $average->sensors);
        $this->assertSame(0, $average->measurements);
    }
}
