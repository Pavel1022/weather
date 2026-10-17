<?php

namespace Tests\Unit\SensorCommunity;

use App\Data\PmReading;
use App\Services\Api\SensorCommunity\ArchiveCsvParser;
use PHPUnit\Framework\TestCase;

final class ArchiveCsvParserTest extends TestCase
{
    /**
     * @return void
     */
    public function test_it_reads_pm25_from_the_tenth_column_of_sds011_files(): void
    {
        $readings = $this->parseFixture('sds011_sample.csv');

        $this->assertCount(7, $readings);
        $this->assertSame([0.3, 0.5, 0.3, 0.83, 0.6], array_slice($this->values($readings), 0, 5));
        $this->assertSame('2026-10-15 00:00:59', $readings[0]->measuredAt->toDateTimeString());
        $this->assertSame('UTC', $readings[0]->measuredAt->timezoneName);
    }

    /**
     * @return void
     */
    public function test_it_keeps_the_sds011_error_code_for_the_filter_to_reject(): void
    {
        $this->assertSame(999.9, $this->parseFixture('sds011_sample.csv')[5]->pm25);
    }

    /**
     * @return void
     */
    public function test_an_empty_pm25_value_becomes_null(): void
    {
        $this->assertNull($this->parseFixture('sds011_sample.csv')[6]->pm25);
    }

    /**
     * @return void
     */
    public function test_it_finds_pm25_in_the_eighth_column_of_pms5003_files(): void
    {
        $this->assertSame([5.0, 5.0, 5.0, 5.0, 5.0], $this->values($this->parseFixture('pms5003_sample.csv')));
    }

    /**
     * @return void
     */
    public function test_it_finds_pm25_in_the_ninth_column_of_sps30_files(): void
    {
        $this->assertSame([5.57, 5.51, 5.65, 5.5, 6.14], $this->values($this->parseFixture('sps30_sample.csv')));
    }

    /**
     * @return void
     */
    public function test_files_without_a_pm25_column_have_no_readings(): void
    {
        $this->assertSame([], $this->parseFixture('bme280_sample.csv'));
    }

    /**
     * @return void
     */
    public function test_a_file_with_only_a_header_has_no_readings(): void
    {
        $this->assertSame([], (new ArchiveCsvParser)->parse("sensor_id;sensor_type;location;lat;lon;timestamp;P1;P2;P0\n"));
    }

    /**
     * @return void
     */
    public function test_an_empty_file_has_no_readings(): void
    {
        $this->assertSame([], (new ArchiveCsvParser)->parse(''));
    }

    /**
     * @return void
     */
    public function test_rows_with_a_broken_timestamp_are_skipped(): void
    {
        $csv = "sensor_id;sensor_type;location;lat;lon;timestamp;P1;P2\n"
            ."1;SDS011;1;42.0;23.0;not-a-date;8;5\n"
            ."1;SDS011;1;42.0;23.0;2026-10-15T00:02:10;8;6\n";

        $this->assertSame([6.0], $this->values((new ArchiveCsvParser)->parse($csv)));
    }

    /**
     * @param  string  $name
     * @return list<PmReading>
     */
    private function parseFixture(string $name): array
    {
        return (new ArchiveCsvParser)->parse((string) file_get_contents(__DIR__.'/../../Fixtures/'.$name));
    }

    /**
     * @param  list<PmReading>  $readings
     * @return list<float|null>
     */
    private function values(array $readings): array
    {
        return array_map(fn (PmReading $reading): ?float => $reading->pm25, $readings);
    }
}
