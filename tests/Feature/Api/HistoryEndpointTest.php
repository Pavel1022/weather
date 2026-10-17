<?php

namespace Tests\Feature\Api;

use App\Data\SensorDayAverage;
use App\Models\Sensor;
use App\Repositories\Contracts\DailyAverageRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HistoryEndpointTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return void
     */
    public function test_an_empty_database_has_no_history(): void
    {
        $this->getJson('/api/history')
            ->assertNotFound()
            ->assertJsonPath('message', 'No air quality data has been imported yet.');
    }

    /**
     * @return void
     */
    public function test_it_returns_the_first_and_last_imported_day(): void
    {
        Sensor::query()->create(['id' => 1, 'type' => 'SDS011', 'city' => 'Sofia', 'latitude' => 42.0, 'longitude' => 23.0, 'distance_km' => 1.0]);

        $this->app->make(DailyAverageRepositoryInterface::class)->saveMany([
            new SensorDayAverage(1, 'Sofia', CarbonImmutable::parse('2026-07-17'), 1000.0, 100, 10.0),
            new SensorDayAverage(1, 'Sofia', CarbonImmutable::parse('2026-10-16'), 1000.0, 100, 10.0),
            new SensorDayAverage(1, 'Sofia', CarbonImmutable::parse('2026-08-01'), 1000.0, 100, 10.0),
        ]);

        $this->getJson('/api/history')
            ->assertOk()
            ->assertExactJson(['from' => '2026-07-17', 'to' => '2026-10-16']);
    }
}
