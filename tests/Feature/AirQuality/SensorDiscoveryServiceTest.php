<?php

namespace Tests\Feature\AirQuality;

use App\Data\SensorData;
use App\Models\Sensor;
use App\Services\AirQuality\SensorDiscoveryService;
use App\Services\Api\SensorCommunity\Contracts\SensorDirectoryInterface;
use App\Services\Api\SensorCommunity\Exceptions\SensorCommunityException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SensorDiscoveryServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var float
     */
    private const KM_PER_DEGREE = 111.195;

    /**
     * @return void
     */
    public function test_it_saves_only_sensors_inside_the_city_radius(): void
    {
        $this->fakeDirectory([
            'Sofia' => [
                $this->sensorNorthOf(1, 42.6977, 23.3219, 5.0),
                $this->sensorNorthOf(2, 42.6977, 23.3219, 12.0),
            ],
        ]);

        $counts = $this->app->make(SensorDiscoveryService::class)->discover();

        $this->assertSame(['Sofia' => 1, 'Plovdiv' => 0, 'Varna' => 0, 'Burgas' => 0, 'Ruse' => 0], $counts);
        $this->assertSame([1], Sensor::query()->pluck('id')->all());
        $this->assertSame('Sofia', Sensor::query()->value('city'));
        $this->assertEqualsWithDelta(5.0, Sensor::query()->value('distance_km'), 0.01);
    }

    /**
     * @return void
     */
    public function test_a_sensor_returned_for_two_cities_is_saved_once(): void
    {
        $shared = $this->sensorNorthOf(7, 43.2141, 27.9147, 3.0);

        $this->fakeDirectory([
            'Varna' => [$shared],
            'Burgas' => [$shared],
        ]);

        $counts = $this->app->make(SensorDiscoveryService::class)->discover();

        $this->assertSame(1, $counts['Varna']);
        $this->assertSame(0, $counts['Burgas']);
        $this->assertSame(1, Sensor::query()->count());
    }

    /**
     * @return void
     */
    public function test_running_discovery_twice_does_not_duplicate_sensors(): void
    {
        $this->fakeDirectory([
            'Ruse' => [$this->sensorNorthOf(3, 43.8356, 25.9657, 2.0)],
        ]);

        $discovery = $this->app->make(SensorDiscoveryService::class);
        $discovery->discover();
        $discovery->discover();

        $this->assertSame(1, Sensor::query()->count());
    }

    /**
     * @return void
     */
    public function test_errors_from_the_directory_are_passed_on(): void
    {
        $this->app->instance(SensorDirectoryInterface::class, new class implements SensorDirectoryInterface
        {
            /**
             * @param  float  $latitude
             * @param  float  $longitude
             * @param  float  $radiusKm
             * @return list<SensorData>
             */
            public function outdoorParticulateSensorsAround(float $latitude, float $longitude, float $radiusKm): array
            {
                throw SensorCommunityException::requestFailed('https://data.sensor.community', 503);
            }
        });

        $this->expectException(SensorCommunityException::class);

        $this->app->make(SensorDiscoveryService::class)->discover();
    }

    /**
     * @param  array<string, list<SensorData>>  $sensorsByCity
     * @return void
     */
    private function fakeDirectory(array $sensorsByCity): void
    {
        $centres = collect(config('air.cities'))->mapWithKeys(
            fn (array $city): array => [$city['name'] => [$city['latitude'], $city['longitude']]],
        )->all();

        $this->app->instance(SensorDirectoryInterface::class, new class($sensorsByCity, $centres) implements SensorDirectoryInterface
        {
            /**
             * @param  array<string, list<SensorData>>  $sensorsByCity
             * @param  array<string, array{float, float}>  $centres
             */
            public function __construct(
                private readonly array $sensorsByCity,
                private readonly array $centres,
            ) {}

            /**
             * @param  float  $latitude
             * @param  float  $longitude
             * @param  float  $radiusKm
             * @return list<SensorData>
             */
            public function outdoorParticulateSensorsAround(float $latitude, float $longitude, float $radiusKm): array
            {
                $city = array_search([$latitude, $longitude], $this->centres, false);

                return $this->sensorsByCity[$city] ?? [];
            }
        });
    }

    /**
     * @param  int  $id
     * @param  float  $latitude
     * @param  float  $longitude
     * @param  float  $kilometers
     * @return SensorData
     */
    private function sensorNorthOf(int $id, float $latitude, float $longitude, float $kilometers): SensorData
    {
        return new SensorData($id, 'SDS011', $latitude + $kilometers / self::KM_PER_DEGREE, $longitude, false);
    }
}
