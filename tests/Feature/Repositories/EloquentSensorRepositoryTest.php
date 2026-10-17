<?php

namespace Tests\Feature\Repositories;

use App\Data\City;
use App\Data\SensorData;
use App\Models\Sensor;
use App\Repositories\Contracts\SensorRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EloquentSensorRepositoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var SensorRepositoryInterface
     */
    private SensorRepositoryInterface $repository;

    /**
     * @var City
     */
    private City $sofia;

    /**
     * @var City
     */
    private City $varna;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->app->make(SensorRepositoryInterface::class);
        $this->sofia = new City('Sofia', 42.6977, 23.3219);
        $this->varna = new City('Varna', 43.2141, 27.9147);
    }

    /**
     * @return void
     */
    public function test_it_saves_a_sensor_with_its_city_and_distance(): void
    {
        $this->repository->save(new SensorData(28910, 'SDS011', 42.642, 23.31, false), $this->sofia, 6.27149);

        $sensor = Sensor::query()->findOrFail(28910);

        $this->assertSame('SDS011', $sensor->type);
        $this->assertSame('Sofia', $sensor->city);
        $this->assertSame(42.642, $sensor->latitude);
        $this->assertSame(23.31, $sensor->longitude);
        $this->assertSame(6.271, $sensor->distance_km);
    }

    /**
     * @return void
     */
    public function test_saving_the_same_sensor_again_updates_it_instead_of_duplicating(): void
    {
        $this->repository->save(new SensorData(28910, 'SDS011', 42.642, 23.31, false), $this->sofia, 6.27);
        $this->repository->save(new SensorData(28910, 'SDS011', 42.65, 23.32, false), $this->sofia, 5.5);

        $this->assertSame(1, $this->repository->count());
        $this->assertSame(42.65, Sensor::query()->findOrFail(28910)->latitude);
    }

    /**
     * @return void
     */
    public function test_cities_by_sensor_id_maps_each_sensor_to_its_city(): void
    {
        $this->repository->save(new SensorData(28910, 'SDS011', 42.642, 23.31, false), $this->sofia, 6.27);
        $this->repository->save(new SensorData(12345, 'SPS30', 43.22, 27.91, false), $this->varna, 0.7);

        $this->assertEqualsCanonicalizing(
            [28910 => 'Sofia', 12345 => 'Varna'],
            $this->repository->citiesBySensorId(),
        );
    }

    /**
     * @return void
     */
    public function test_an_empty_table_has_no_sensors(): void
    {
        $this->assertSame(0, $this->repository->count());
        $this->assertSame([], $this->repository->citiesBySensorId());
    }
}
