<?php

namespace App\Services\Api\SensorCommunity\Exceptions;

use RuntimeException;
use Throwable;

final class SensorCommunityException extends RuntimeException
{
    /**
     * @param  string  $url
     * @param  int  $status
     * @return self
     */
    public static function requestFailed(string $url, int $status): self
    {
        return new self("Sensor.Community request to {$url} failed with HTTP status {$status}.");
    }

    /**
     * @param  string  $url
     * @param  Throwable  $previous
     * @return self
     */
    public static function connectionFailed(string $url, Throwable $previous): self
    {
        return new self("Could not connect to Sensor.Community at {$url}: {$previous->getMessage()}", 0, $previous);
    }

    /**
     * @param  string  $url
     * @param  string  $reason
     * @return self
     */
    public static function invalidResponse(string $url, string $reason): self
    {
        return new self("Sensor.Community returned an invalid response from {$url}: {$reason}");
    }

    /**
     * @param  string  $date
     * @return self
     */
    public static function dayNotAvailable(string $date): self
    {
        return new self("The Sensor.Community archive has no files for {$date} yet.");
    }
}
