<?php

namespace App\Services\Api\SensorCommunity;

use App\Data\PmReading;
use Carbon\CarbonImmutable;
use Throwable;

final class ArchiveCsvParser
{
    /**
     * @var string
     */
    private const DELIMITER = ';';

    /**
     * @var string
     */
    private const PM25_COLUMN = 'P2';

    /**
     * @var string
     */
    private const TIMESTAMP_COLUMN = 'timestamp';

    /**
     * @param  string  $csv
     * @return list<PmReading>
     */
    public function parse(string $csv): array
    {
        $lines = preg_split('/\r\n|\n|\r/', trim($csv));

        if ($lines === false || $lines === [] || $lines[0] === '') {
            return [];
        }

        $columns = str_getcsv(array_shift($lines), self::DELIMITER, '"', '');
        $pm25Index = array_search(self::PM25_COLUMN, $columns, true);
        $timestampIndex = array_search(self::TIMESTAMP_COLUMN, $columns, true);

        if ($pm25Index === false || $timestampIndex === false) {
            return [];
        }

        $readings = [];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $fields = str_getcsv($line, self::DELIMITER, '"', '');
            $timestamp = trim($fields[$timestampIndex] ?? '');

            if ($timestamp === '') {
                continue;
            }

            try {
                $measuredAt = CarbonImmutable::parse($timestamp, 'UTC');
            } catch (Throwable) {
                continue;
            }

            $readings[] = new PmReading($measuredAt, $this->toFloat($fields[$pm25Index] ?? null));
        }

        return $readings;
    }

    /**
     * @param  string|null  $value
     * @return float|null
     */
    private function toFloat(?string $value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }
}
