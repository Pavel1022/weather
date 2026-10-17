<?php

namespace Tests\Feature\SensorCommunity;

use App\Services\Api\SensorCommunity\Contracts\SensorArchiveInterface;
use App\Services\Api\SensorCommunity\Exceptions\SensorCommunityException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class SensorCommunityArchiveClientTest extends TestCase
{
    /**
     * @var SensorArchiveInterface
     */
    private SensorArchiveInterface $archive;

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

        $this->archive = $this->app->make(SensorArchiveInterface::class);
        $this->day = CarbonImmutable::parse('2026-10-15');
    }

    /**
     * @return void
     */
    public function test_it_lists_the_unique_csv_files_of_a_day(): void
    {
        Http::fake([
            'archive.sensor.community/2026-10-15/' => Http::response((string) file_get_contents(base_path('tests/Fixtures/archive_day.html'))),
        ]);

        $this->assertSame([
            '2026-10-15_bme280_sensor_28911.csv',
            '2026-10-15_pms5003_sensor_12345.csv',
            '2026-10-15_sds011_sensor_28910.csv',
            '2026-10-15_sds011_sensor_28910_indoor.csv',
            '2026-10-15_sds011_sensor_99999.csv',
        ], $this->archive->filesForDate($this->day));
    }

    /**
     * @return void
     */
    public function test_a_day_that_is_not_published_yet_has_no_files(): void
    {
        Http::fake([
            'archive.sensor.community/*' => Http::response('Not Found', 404),
        ]);

        $this->assertSame([], $this->archive->filesForDate($this->day));
    }

    /**
     * @return void
     */
    public function test_a_server_error_on_the_listing_becomes_a_sensor_community_exception(): void
    {
        Http::fake([
            'archive.sensor.community/*' => Http::response('', 503),
        ]);

        $this->expectException(SensorCommunityException::class);

        $this->archive->filesForDate($this->day);
    }

    /**
     * @return void
     */
    public function test_it_downloads_files_and_marks_failed_ones_as_null(): void
    {
        Http::fake([
            'archive.sensor.community/2026-10-15/ok.csv' => Http::response('sensor_id;P2'),
            'archive.sensor.community/2026-10-15/broken.csv' => Http::response('', 500),
            'archive.sensor.community/2026-10-15/offline.csv' => fn () => throw new ConnectionException('timeout'),
        ]);

        $result = $this->archive->downloadMany($this->day, ['ok.csv', 'broken.csv', 'offline.csv']);

        $this->assertSame(['ok.csv' => 'sensor_id;P2', 'broken.csv' => null, 'offline.csv' => null], $result);
    }

    /**
     * @return void
     */
    public function test_it_downloads_in_batches_bigger_than_the_concurrency(): void
    {
        config(['air.sensor_community.concurrency' => 2]);
        $archive = $this->app->make(SensorArchiveInterface::class);

        Http::fake([
            'archive.sensor.community/*' => Http::response('data'),
        ]);

        $names = ['a.csv', 'b.csv', 'c.csv', 'd.csv', 'e.csv'];

        $this->assertSame(array_fill_keys($names, 'data'), $archive->downloadMany($this->day, $names));
        Http::assertSentCount(5);
    }
}
