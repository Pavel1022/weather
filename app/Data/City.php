<?php

namespace App\Data;

final readonly class City
{
    /**
     * @param  string  $name
     * @param  float  $latitude
     * @param  float  $longitude
     */
    public function __construct(
        public string $name,
        public float $latitude,
        public float $longitude,
    ) {}
}
