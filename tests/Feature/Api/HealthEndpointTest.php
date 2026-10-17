<?php

namespace Tests\Feature\Api;

use App\Data\ImportResult;
use App\Models\Sensor;
use App\Repositories\Contracts\ImportRunRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HealthEndpointTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return void
     */
    public function test_it_reports_a_healthy_service_before_any_import(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertExactJson([
                'status' => 'ok',
                'database' => 'ok',
                'sensors' => 0,
                'last_import' => null,
            ]);
    }

    /**
     * @return void
     */
    public function test_it_reports_sensors_and_the_last_completed_import(): void
    {
        Sensor::query()->create(['id' => 1, 'type' => 'SDS011', 'city' => 'Sofia', 'latitude' => 42.0, 'longitude' => 23.0, 'distance_km' => 1.0]);
        $runs = $this->app->make(ImportRunRepositoryInterface::class);
        $runs->markCompleted(new ImportResult(CarbonImmutable::parse('2026-10-16'), 1, 0, 500, 0, 1, false));
        $runs->markFailed(CarbonImmutable::parse('2026-10-17'), 'Archive unavailable');

        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonStructure(['status', 'database', 'sensors', 'last_import'])
            ->assertJsonPath('sensors', 1)
            ->assertJsonPath('last_import', '2026-10-16');
    }
}
