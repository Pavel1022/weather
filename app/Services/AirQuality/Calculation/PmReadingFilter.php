<?php

namespace App\Services\AirQuality\Calculation;

final class PmReadingFilter
{
    /**
     * @param  float  $minimum
     * @param  float  $maximum
     */
    public function __construct(
        private readonly float $minimum,
        private readonly float $maximum,
    ) {}

    /**
     * @param  float|null  $pm25
     * @return bool
     */
    public function isValid(?float $pm25): bool
    {
        if ($pm25 === null) {
            return false;
        }

        return $pm25 > $this->minimum && $pm25 < $this->maximum;
    }
}
