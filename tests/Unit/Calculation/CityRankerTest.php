<?php

namespace Tests\Unit\Calculation;

use App\Data\CityAverage;
use App\Data\DateRange;
use App\Data\Ranking;
use App\Services\AirQuality\Calculation\CityRanker;
use PHPUnit\Framework\TestCase;

final class CityRankerTest extends TestCase
{
    /**
     * @var DateRange
     */
    private DateRange $period;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->period = DateRange::fromStrings('2026-09-01', '2026-09-30');
    }

    /**
     * @return void
     */
    public function test_cities_are_ordered_from_cleanest_to_dirtiest(): void
    {
        $ranking = (new CityRanker)->rank($this->period, [
            $this->city('Sofia', 23.7),
            $this->city('Varna', 8.4),
            $this->city('Ruse', 18.3),
        ]);

        $this->assertSame(['Varna', 'Ruse', 'Sofia'], $this->names($ranking));
        $this->assertSame('Varna', $ranking->cleanest()?->city);
        $this->assertSame($this->period, $ranking->period);
    }

    /**
     * @return void
     */
    public function test_cities_without_data_are_listed_last(): void
    {
        $ranking = (new CityRanker)->rank($this->period, [
            $this->city('Burgas', null),
            $this->city('Sofia', 23.7),
            $this->city('Ahtopol', null),
            $this->city('Varna', 8.4),
        ]);

        $this->assertSame(['Varna', 'Sofia', 'Ahtopol', 'Burgas'], $this->names($ranking));
    }

    /**
     * @return void
     */
    public function test_equal_averages_are_ordered_by_name(): void
    {
        $ranking = (new CityRanker)->rank($this->period, [
            $this->city('Ruse', 18.3),
            $this->city('Plovdiv', 18.3),
        ]);

        $this->assertSame(['Plovdiv', 'Ruse'], $this->names($ranking));
    }

    /**
     * @return void
     */
    public function test_there_is_no_cleanest_city_without_data(): void
    {
        $this->assertNull((new CityRanker)->rank($this->period, [$this->city('Ruse', null)])->cleanest());
        $this->assertSame([], (new CityRanker)->rank($this->period, [])->cities);
    }

    /**
     * @param  string  $name
     * @param  float|null  $average
     * @return CityAverage
     */
    private function city(string $name, ?float $average): CityAverage
    {
        return new CityAverage($name, $average, $average === null ? 0 : 1, $average === null ? 0 : 100);
    }

    /**
     * @param  Ranking  $ranking
     * @return list<string>
     */
    private function names(Ranking $ranking): array
    {
        return array_map(fn (CityAverage $city): string => $city->city, $ranking->cities);
    }
}
