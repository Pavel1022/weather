<?php

namespace App\Services\Api\SensorCommunity\Contracts;

use App\Services\Api\SensorCommunity\Exceptions\SensorCommunityException;
use Carbon\CarbonImmutable;

interface SensorArchiveInterface
{
    /**
     * @param  CarbonImmutable  $date
     * @return list<string>
     *
     * @throws SensorCommunityException
     */
    public function filesForDate(CarbonImmutable $date): array;

    /**
     * @param  CarbonImmutable  $date
     * @param  list<string>  $fileNames
     * @return array<string, string|null>
     */
    public function downloadMany(CarbonImmutable $date, array $fileNames): array;
}
