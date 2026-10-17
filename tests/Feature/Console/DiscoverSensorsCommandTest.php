<?php

namespace Tests\Feature\Console;

use App\Models\Sensor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class DiscoverSensorsCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config(['air.sensor_community.retries' => 1]);
    }

    /**
     * @return void
     */
    public function test_it_discovers_and_reports_sensors_per_city(): void
    {
        Http::fake([
            'data.sensor.community/airrohr/v1/filter/area=42.697700*' => Http::response(
                json_decode((string) file_get_contents(base_path('tests/Fixtures/area_sofia.json')), true),
            ),
            'data.sensor.community/*' => Http::response([]),
        ]);

        $this->artisan('air:discover')
            ->expectsTable(['City', 'Sensors'], [
                ['Sofia', 2],
                ['Plovdiv', 0],
                ['Varna', 0],
                ['Burgas', 0],
                ['Ruse', 0],
            ])
            ->expectsOutputToContain('Total sensors: 2')
            ->assertSuccessful();

        $this->assertSame(2, Sensor::query()->count());
    }

    /**
     * @return void
     */
    public function test_it_fails_when_the_api_is_down(): void
    {
        Http::fake(['data.sensor.community/*' => Http::response('', 503)]);

        $this->artisan('air:discover')
            ->expectsOutputToContain('HTTP status 503')
            ->assertFailed();
    }
}
