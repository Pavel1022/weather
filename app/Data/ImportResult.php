<?php

namespace App\Data;

use Carbon\CarbonImmutable;

final readonly class ImportResult
{
    /**
     * @param  CarbonImmutable  $date
     * @param  int  $files
     * @param  int  $failedFiles
     * @param  int  $readings
     * @param  int  $rejectedReadings
     * @param  int  $sensorDays
     * @param  bool  $skipped
     */
    public function __construct(
        public CarbonImmutable $date,
        public int $files,
        public int $failedFiles,
        public int $readings,
        public int $rejectedReadings,
        public int $sensorDays,
        public bool $skipped,
    ) {}
}
