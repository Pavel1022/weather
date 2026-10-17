<?php

namespace App\Data;

final readonly class SensorData
{
    /**
     * @param  int  $id
     * @param  string  $type
     * @param  float  $latitude
     * @param  float  $longitude
     * @param  bool  $indoor
     */
    public function __construct(
        public int $id,
        public string $type,
        public float $latitude,
        public float $longitude,
        public bool $indoor,
    ) {}
}
