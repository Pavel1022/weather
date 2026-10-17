<?php

namespace Tests\Unit\SensorCommunity;

use App\Services\Api\SensorCommunity\ArchiveFileNameParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ArchiveFileNameParserTest extends TestCase
{
    /**
     * @return void
     */
    public function test_it_reads_an_outdoor_sensor_file_name(): void
    {
        $file = (new ArchiveFileNameParser)->parse('2026-10-15_sds011_sensor_28910.csv');

        $this->assertNotNull($file);
        $this->assertSame('2026-10-15_sds011_sensor_28910.csv', $file->name);
        $this->assertSame('2026-10-15', $file->date->toDateString());
        $this->assertSame('sds011', $file->sensorType);
        $this->assertSame(28910, $file->sensorId);
        $this->assertFalse($file->indoor);
    }

    /**
     * @return void
     */
    public function test_it_recognises_indoor_sensors(): void
    {
        $file = (new ArchiveFileNameParser)->parse('2026-10-15_sds011_sensor_28910_indoor.csv');

        $this->assertNotNull($file);
        $this->assertTrue($file->indoor);
    }

    /**
     * @return void
     */
    public function test_it_reads_other_sensor_types(): void
    {
        $this->assertSame('bme280', (new ArchiveFileNameParser)->parse('2026-10-15_bme280_sensor_1.csv')?->sensorType);
        $this->assertSame('sps30', (new ArchiveFileNameParser)->parse('2026-10-15_sps30_sensor_77.csv')?->sensorType);
    }

    /**
     * @param  string  $fileName
     * @return void
     */
    #[DataProvider('invalidNames')]
    public function test_it_ignores_names_that_are_not_sensor_files(string $fileName): void
    {
        $this->assertNull((new ArchiveFileNameParser)->parse($fileName));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidNames(): array
    {
        return [
            'html page' => ['index.html'],
            'sort link' => ['?C=N;O=D'],
            'missing sensor id' => ['2026-10-15_sds011_sensor_.csv'],
            'wrong date format' => ['15-10-2026_sds011_sensor_1.csv'],
        ];
    }
}
