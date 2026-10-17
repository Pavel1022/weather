<?php

namespace App\Services\Api\SensorCommunity;

use App\Data\ArchiveFile;
use Carbon\CarbonImmutable;

final class ArchiveFileNameParser
{
    /**
     * @var string
     */
    private const PATTERN = '/^(\d{4}-\d{2}-\d{2})_([a-z0-9]+)_sensor_(\d+)(_indoor)?\.csv$/';

    /**
     * @param  string  $fileName
     * @return ArchiveFile|null
     */
    public function parse(string $fileName): ?ArchiveFile
    {
        if (preg_match(self::PATTERN, $fileName, $matches) !== 1) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('Y-m-d', $matches[1]);

        if ($date === null) {
            return null;
        }

        return new ArchiveFile(
            $fileName,
            $date->startOfDay(),
            $matches[2],
            (int) $matches[3],
            ($matches[4] ?? '') !== '',
        );
    }
}
