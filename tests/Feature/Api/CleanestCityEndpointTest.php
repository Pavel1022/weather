<?php

namespace Tests\Feature\Api;

use App\Data\SensorDayAverage;
use App\Models\Sensor;
use App\Models\SensorDailyAverage;
use App\Repositories\Contracts\DailyAverageRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CleanestCityEndpointTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-17 12:00:00'));

        $this->sensor(1, 'Sofia');
        $this->sensor(2, 'Sofia');
        $this->sensor(3, 'Varna');
        $this->sensor(4, 'Plovdiv');

        $days = [];

        foreach (['2026-10-10', '2026-10-11', '2026-10-12', '2026-10-13', '2026-10-14'] as $date) {
            $days[] = $this->day(1, 'Sofia', $date, 10.0);
            $days[] = $this->day(2, 'Sofia', $date, 14.0);
            $days[] = $this->day(3, 'Varna', $date, 6.0);
            $days[] = $this->day(4, 'Plovdiv', $date, 20.0);
        }

        $this->app->make(DailyAverageRepositoryInterface::class)->saveMany($days);
    }

    /**
     * @return void
     */
    public function test_it_returns_the_ranking_for_a_valid_period(): void
    {
        $this->getJson('/api/cleanest-city?from=2026-10-10&to=2026-10-14')
            ->assertOk()
            ->assertJsonPath('from', '2026-10-10')
            ->assertJsonPath('to', '2026-10-14')
            ->assertJsonCount(5, 'ranking')
            ->assertJsonPath('ranking.0', ['city' => 'Varna', 'avg_pm25' => 6, 'sensors' => 1, 'measurements' => 500])
            ->assertJsonPath('ranking.1.city', 'Sofia')
            ->assertJsonPath('ranking.1.avg_pm25', 12)
            ->assertJsonPath('ranking.1.sensors', 2)
            ->assertJsonPath('ranking.2.city', 'Plovdiv');
    }

    /**
     * @return void
     */
    public function test_cities_without_data_are_listed_last_with_null(): void
    {
        $this->getJson('/api/cleanest-city?from=2026-10-10&to=2026-10-14')
            ->assertOk()
            ->assertJsonPath('ranking.3', ['city' => 'Burgas', 'avg_pm25' => null, 'sensors' => 0, 'measurements' => 0])
            ->assertJsonPath('ranking.4.city', 'Ruse')
            ->assertJsonPath('ranking.4.avg_pm25', null);
    }

    /**
     * @return void
     */
    public function test_a_single_day_period_is_allowed(): void
    {
        $this->getJson('/api/cleanest-city?from=2026-10-12&to=2026-10-12')
            ->assertOk()
            ->assertJsonPath('ranking.0.city', 'Varna');
    }

    /**
     * @return void
     */
    public function test_the_response_has_no_data_wrapper(): void
    {
        $this->getJson('/api/cleanest-city?from=2026-10-10&to=2026-10-14')
            ->assertOk()
            ->assertJsonMissingPath('data')
            ->assertJsonStructure(['from', 'to', 'ranking' => [['city', 'avg_pm25', 'sensors', 'measurements']]]);
    }

    /**
     * @return void
     */
    public function test_an_end_date_before_the_start_date_is_rejected(): void
    {
        $this->getJson('/api/cleanest-city?from=2026-10-14&to=2026-10-10')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['to' => 'The end date must be on or after the start date.']);
    }

    /**
     * @return void
     */
    public function test_missing_dates_are_rejected(): void
    {
        $this->getJson('/api/cleanest-city')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['from' => 'Choose a start date.', 'to' => 'Choose an end date.']);
    }

    /**
     * @return void
     */
    public function test_a_wrong_date_format_is_rejected(): void
    {
        $this->getJson('/api/cleanest-city?from=2026/10/10&to=2026-10-14')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['from' => 'The start date must be in the YYYY-MM-DD format.']);
    }

    /**
     * @return void
     */
    public function test_a_future_date_is_rejected(): void
    {
        $this->getJson('/api/cleanest-city?from=2026-10-10&to=2026-10-20')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['to' => 'The end date cannot be in the future.']);
    }

    /**
     * @return void
     */
    public function test_a_period_outside_the_stored_history_is_rejected(): void
    {
        $this->getJson('/api/cleanest-city?from=2026-10-01&to=2026-10-12')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['from' => 'Data is available from 2026-10-10 to 2026-10-14.']);
    }

    /**
     * @return void
     */
    public function test_an_empty_database_gives_a_clear_error(): void
    {
        SensorDailyAverage::query()->delete();

        $this->getJson('/api/cleanest-city?from=2026-10-10&to=2026-10-14')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['from' => 'No air quality data has been imported yet.']);
    }

    /**
     * @param  int  $id
     * @param  string  $city
     * @return void
     */
    private function sensor(int $id, string $city): void
    {
        Sensor::query()->create(['id' => $id, 'type' => 'SDS011', 'city' => $city, 'latitude' => 42.0, 'longitude' => 23.0, 'distance_km' => 1.0]);
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
}
