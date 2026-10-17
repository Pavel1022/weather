<?php

namespace Tests\Feature\SensorCommunity;

use App\Data\SensorData;
use App\Services\Api\SensorCommunity\Contracts\SensorDirectoryInterface;
use App\Services\Api\SensorCommunity\Exceptions\SensorCommunityException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class SensorCommunityApiClientTest extends TestCase
{
    /**
     * @var SensorDirectoryInterface
     */
    private SensorDirectoryInterface $client;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config(['air.sensor_community.retries' => 1]);

        $this->client = $this->app->make(SensorDirectoryInterface::class);
    }

    /**
     * @return void
     */
    public function test_it_returns_unique_outdoor_particulate_sensors(): void
    {
        Http::fake([
            'data.sensor.community/*' => Http::response($this->fixture()),
        ]);

        $sensors = $this->client->outdoorParticulateSensorsAround(42.6977, 23.3219, 10.0);

        $this->assertSame([28910, 30001], array_map(fn (SensorData $sensor): int => $sensor->id, $sensors));
        $this->assertSame('SDS011', $sensors[0]->type);
        $this->assertSame(42.642, $sensors[0]->latitude);
        $this->assertSame(23.31, $sensors[0]->longitude);
        $this->assertFalse($sensors[0]->indoor);
    }

    /**
     * @return void
     */
    public function test_it_asks_for_the_area_around_the_given_point(): void
    {
        Http::fake([
            'data.sensor.community/*' => Http::response([]),
        ]);

        $this->client->outdoorParticulateSensorsAround(42.6977, 23.3219, 10.0);

        Http::assertSent(fn (Request $request): bool => str_ends_with(
            $request->url(),
            '/airrohr/v1/filter/area=42.697700,23.321900,10.000000',
        ));
    }

    /**
     * @return void
     */
    public function test_a_server_error_becomes_a_sensor_community_exception(): void
    {
        Http::fake([
            'data.sensor.community/*' => Http::response('', 500),
        ]);

        $this->expectException(SensorCommunityException::class);
        $this->expectExceptionMessage('HTTP status 500');

        $this->client->outdoorParticulateSensorsAround(42.6977, 23.3219, 10.0);
    }

    /**
     * @return void
     */
    public function test_a_connection_problem_becomes_a_sensor_community_exception(): void
    {
        Http::fake([
            'data.sensor.community/*' => fn () => throw new ConnectionException('timeout'),
        ]);

        $this->expectException(SensorCommunityException::class);

        $this->client->outdoorParticulateSensorsAround(42.6977, 23.3219, 10.0);
    }

    /**
     * @return void
     */
    public function test_a_non_array_response_is_rejected(): void
    {
        Http::fake([
            'data.sensor.community/*' => Http::response('"maintenance"'),
        ]);

        $this->expectException(SensorCommunityException::class);

        $this->client->outdoorParticulateSensorsAround(42.6977, 23.3219, 10.0);
    }

    /**
     * @return array<int, mixed>
     */
    private function fixture(): array
    {
        return json_decode((string) file_get_contents(base_path('tests/Fixtures/area_sofia.json')), true);
    }
}
