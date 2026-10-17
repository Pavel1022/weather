<?php

namespace Tests\Feature\Console;

use App\Models\ImportRun;
use App\Models\Sensor;
use App\Models\SensorDailyAverage;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ImportAirQualityCommandTest extends TestCase
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

        Sensor::query()->create(['id' => 28910, 'type' => 'SDS011', 'city' => 'Sofia', 'latitude' => 42.642, 'longitude' => 23.31, 'distance_km' => 6.27]);
    }

    /**
     * @return void
     */
    public function test_it_imports_every_day_of_the_period(): void
    {
        Http::fake([
            'archive.sensor.community/*/' => fn ($request) => Http::response($this->listing($this->dayFromUrl($request->url()))),
            'archive.sensor.community/*.csv' => fn ($request) => Http::response($this->csv($this->dayFromUrl($request->url()))),
        ]);

        $this->artisan('air:import', ['--from' => '2026-10-01', '--to' => '2026-10-03'])
            ->expectsOutputToContain('Importing 2026-10-01 to 2026-10-03 (3 days)')
            ->assertSuccessful();

        $this->assertSame(3, SensorDailyAverage::query()->count());
        $this->assertSame(3, ImportRun::query()->where('status', ImportRun::STATUS_COMPLETED)->count());
    }

    /**
     * @return void
     */
    public function test_days_that_are_not_in_the_archive_make_the_command_fail(): void
    {
        Http::fake(['archive.sensor.community/*' => Http::response('Not Found', 404)]);

        $this->artisan('air:import', ['--from' => '2026-10-01', '--to' => '2026-10-01'])
            ->expectsOutputToContain('2026-10-01: The Sensor.Community archive has no files for 2026-10-01 yet.')
            ->assertFailed();
    }

    /**
     * @return void
     */
    public function test_the_days_option_ends_yesterday(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-17 12:00'));
        Http::fake(['archive.sensor.community/*' => Http::response('Not Found', 404)]);

        $this->artisan('air:import', ['--days' => 2])
            ->expectsOutputToContain('Importing 2026-10-15 to 2026-10-16 (2 days)')
            ->assertFailed();
    }

    /**
     * @return void
     */
    public function test_an_end_date_before_the_start_date_is_rejected(): void
    {
        $this->artisan('air:import', ['--from' => '2026-10-05', '--to' => '2026-10-01'])
            ->expectsOutputToContain('The start date must be on or before the end date.')
            ->assertFailed();
    }

    /**
     * @return void
     */
    public function test_badly_formatted_dates_are_rejected(): void
    {
        $this->artisan('air:import', ['--from' => '01.10.2026', '--to' => '2026-10-02'])
            ->expectsOutputToContain('Dates must be in the YYYY-MM-DD format.')
            ->assertFailed();
    }

    /**
     * @return void
     */
    public function test_only_one_of_from_and_to_is_rejected(): void
    {
        $this->artisan('air:import', ['--from' => '2026-10-01'])
            ->expectsOutputToContain('Use --from and --to together.')
            ->assertFailed();
    }

    /**
     * @return void
     */
    public function test_a_non_numeric_number_of_days_is_rejected(): void
    {
        $this->artisan('air:import', ['--days' => 'abc'])
            ->expectsOutputToContain('--days must be a positive number.')
            ->assertFailed();
    }

    /**
     * @param  string  $url
     * @return string
     */
    private function dayFromUrl(string $url): string
    {
        preg_match('#/(\d{4}-\d{2}-\d{2})/#', $url, $matches);

        return $matches[1];
    }

    /**
     * @param  string  $day
     * @return string
     */
    private function listing(string $day): string
    {
        return "<a href=\"{$day}_sds011_sensor_28910.csv\">file</a>";
    }

    /**
     * @param  string  $day
     * @return string
     */
    private function csv(string $day): string
    {
        $lines = ['sensor_id;sensor_type;location;lat;lon;timestamp;P1;durP1;ratioP1;P2;durP2;ratioP2'];
        $start = CarbonImmutable::parse($day);

        for ($i = 0; $i < 120; $i++) {
            $lines[] = '28910;SDS011;16028;42.642;23.310;'.$start->addSeconds($i * 150)->format('Y-m-d\TH:i:s').';20.00;;;10.00;;';
        }

        return implode("\n", $lines);
    }
}
