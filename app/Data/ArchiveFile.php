<?php

namespace App\Data;

use Carbon\CarbonImmutable;

final readonly class ArchiveFile
{
    /**
     * @param  string  $name
     * @param  CarbonImmutable  $date
     * @param  string  $sensorType
     * @param  int  $sensorId
     * @param  bool  $indoor
     */
    public function __construct(
        public string $name,
        public CarbonImmutable $date,
        public string $sensorType,
        public int $sensorId,
        public bool $indoor,
    ) {}
}
