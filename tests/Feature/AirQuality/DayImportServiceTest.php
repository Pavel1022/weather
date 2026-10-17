<?php

namespace Tests\Feature\AirQuality;

use App\Data\ImportResult;
use App\Models\ImportRun;
use App\Models\Sensor;
use App\Models\SensorDailyAverage;
use App\Services\AirQuality\DayImportService;
use App\Services\Api\SensorCommunity\Exceptions\SensorCommunityException;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class DayImportServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var string
     */
    private const DAY_URL = 'archive.sensor.community/2026-10-15/';

    /**
     * @var string
     */
    private const SOFIA_FILE = '2026-10-15_sds011_sensor_28910.csv';

    /**
     * @var string
     */
    private const VARNA_FILE = '2026-10-15_pms5003_sensor_12345.csv';

    /**
     * @var CarbonImmutable
     */
    private CarbonImmutable $day;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config(['air.sensor_community.retries' => 1]);

        $this->day = CarbonImmutable::parse('2026-10-15');

        Sensor::query()->create(['id' => 28910, 'type' => 'SDS011', 'city' => 'Sofia', 'latitude' => 42.642, 'longitude' => 23.31, 'distance_km' => 6.27]);
        Sensor::query()->create(['id' => 12345, 'type' => 'PMS5003', 'city' => 'Varna', 'latitude' => 43.22, 'longitude' => 27.91, 'distance_km' => 0.7]);
    }

    /**
     * @return void
     */
    public function test_it_imports_only_outdoor_files_of_known_sensors(): void
    {
        $this->fakeArchive([
            self::SOFIA_FILE => Http::response($this->sds011Csv(120, 12.0)),
            self::VARNA_FILE => Http::response($this->pms5003Csv(120, 8.0)),
        ]);

        $result = $this->import();

        $this->assertSame(2, $result->files);
        $this->assertSame(0, $result->failedFiles);
        $this->assertSame(240, $result->readings);
        $this->assertSame(2, $result->sensorDays);
        $this->assertFalse($result->skipped);

        $this->assertSame(2, SensorDailyAverage::query()->count());
        $this->assertSame(12.0, SensorDailyAverage::query()->where('sensor_id', 28910)->value('pm25_avg'));
        $this->assertSame(8.0, SensorDailyAverage::query()->where('sensor_id', 12345)->value('pm25_avg'));
        $this->assertDatabaseHas('import_runs', ['status' => ImportRun::STATUS_COMPLETED, 'files' => 2, 'sensor_days' => 2]);
        Http::assertSentCount(3);
    }

    /**
     * @return void
     */
    public function test_a_completed_day_is_skipped_without_downloading_again(): void
    {
        $this->fakeArchive([
            self::SOFIA_FILE => Http::response($this->sds011Csv(120, 12.0)),
            self::VARNA_FILE => Http::response($this->pms5003Csv(120, 8.0)),
        ]);

        $this->import();
        $second = $this->import();

        $this->assertTrue($second->skipped);
        Http::assertSentCount(3);
    }

    /**
     * @return void
     */
    public function test_forcing_a_reimport_replaces_the_rows_instead_of_duplicating_them(): void
    {
        $this->fakeArchive([
            self::SOFIA_FILE => Http::response($this->sds011Csv(120, 12.0)),
            self::VARNA_FILE => Http::response($this->pms5003Csv(120, 8.0)),
        ]);

        $this->import();
        $this->app->make(DayImportService::class)->import($this->day, force: true);

        $this->assertSame(2, SensorDailyAverage::query()->count());
        $this->assertDatabaseCount('import_runs', 1);
    }

    /**
     * @return void
     */
    public function test_a_sensor_with_only_error_values_gets_no_daily_average(): void
    {
        $this->fakeArchive([
            self::SOFIA_FILE => Http::response($this->sds011Csv(120, 999.9)),
            self::VARNA_FILE => Http::response($this->pms5003Csv(120, 8.0)),
        ]);

        $result = $this->import();

        $this->assertSame(1, $result->sensorDays);
        $this->assertSame(120, $result->rejectedReadings);
        $this->assertSame([12345], SensorDailyAverage::query()->pluck('sensor_id')->all());
    }

    /**
     * @return void
     */
    public function test_a_failed_download_is_counted_but_does_not_stop_the_day(): void
    {
        $this->fakeArchive([
            self::SOFIA_FILE => Http::response('', 500),
            self::VARNA_FILE => Http::response($this->pms5003Csv(120, 8.0)),
        ]);

        $result = $this->import();

        $this->assertSame(1, $result->failedFiles);
        $this->assertSame(1, $result->sensorDays);
        $this->assertDatabaseHas('import_runs', ['status' => ImportRun::STATUS_COMPLETED, 'failed_files' => 1]);
    }

    /**
     * @return void
     */
    public function test_a_day_missing_from_the_archive_is_marked_as_failed(): void
    {
        Http::fake([self::DAY_URL => Http::response('Not Found', 404)]);

        try {
            $this->import();
            $this->fail('Expected a SensorCommunityException.');
        } catch (SensorCommunityException) {
            $this->assertDatabaseHas('import_runs', ['status' => ImportRun::STATUS_FAILED]);
            $this->assertSame(0, SensorDailyAverage::query()->count());
        }
    }

    /**
     * @return ImportResult
     */
    private function import(): ImportResult
    {
        return $this->app->make(DayImportService::class)->import($this->day);
    }

    /**
     * @param  array<string, mixed>  $files
     * @return void
     */
    private function fakeArchive(array $files): void
    {
        $responses = [self::DAY_URL => Http::response((string) file_get_contents(base_path('tests/Fixtures/archive_day.html')))];

        foreach ($files as $name => $response) {
            $responses[self::DAY_URL.$name] = $response;
        }

        Http::fake($responses);
    }

    /**
     * @param  int  $rows
     * @param  float  $pm25
     * @return string
     */
    private function sds011Csv(int $rows, float $pm25): string
    {
        $lines = ['sensor_id;sensor_type;location;lat;lon;timestamp;P1;durP1;ratioP1;P2;durP2;ratioP2'];

        for ($i = 0; $i < $rows; $i++) {
            $lines[] = sprintf('28910;SDS011;16028;42.642;23.310;%s;20.00;;;%.2f;;', $this->timestamp($i), $pm25);
        }

        return implode("\n", $lines);
    }

    /**
     * @param  int  $rows
     * @param  float  $pm25
     * @return string
     */
    private function pms5003Csv(int $rows, float $pm25): string
    {
        $lines = ['sensor_id;sensor_type;location;lat;lon;timestamp;P1;P2;P0'];

        for ($i = 0; $i < $rows; $i++) {
            $lines[] = sprintf('12345;PMS5003;5000;43.220;27.910;%s;10;%.2f;', $this->timestamp($i), $pm25);
        }

        return implode("\n", $lines);
    }

    /**
     * @param  int  $index
     * @return string
     */
    private function timestamp(int $index): string
    {
        return $this->day->addSeconds($index * 150)->format('Y-m-d\TH:i:s');
    }
}
