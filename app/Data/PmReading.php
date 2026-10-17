<?php

namespace App\Data;

use Carbon\CarbonImmutable;

final readonly class PmReading
{
    /**
     * @param  CarbonImmutable  $measuredAt
     * @param  float|null  $pm25
     */
    public function __construct(
        public CarbonImmutable $measuredAt,
        public ?float $pm25,
    ) {}
}
