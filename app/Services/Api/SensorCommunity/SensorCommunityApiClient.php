<?php

namespace App\Services\Api\SensorCommunity;

use App\Data\SensorData;
use App\Services\Api\SensorCommunity\Contracts\SensorDirectoryInterface;
use App\Services\Api\SensorCommunity\Exceptions\SensorCommunityException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class SensorCommunityApiClient implements SensorDirectoryInterface
{
    /**
     * @var string
     */
    private const PM25_VALUE_TYPE = 'P2';

    /**
     * @param  string  $baseUrl
     * @param  int  $timeout
     * @param  int  $retries
     * @param  int  $retryDelayMs
     */
    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeout,
        private readonly int $retries,
        private readonly int $retryDelayMs,
    ) {}

    /**
     * @param  float  $latitude
     * @param  float  $longitude
     * @param  float  $radiusKm
     * @return list<SensorData>
     *
     * @throws SensorCommunityException
     */
    public function outdoorParticulateSensorsAround(float $latitude, float $longitude, float $radiusKm): array
    {
        $path = sprintf('/airrohr/v1/filter/area=%F,%F,%F', $latitude, $longitude, $radiusKm);
        $url = rtrim($this->baseUrl, '/').$path;

        try {
            $response = Http::baseUrl($this->baseUrl)
                ->acceptJson()
                ->timeout($this->timeout)
                ->retry($this->retries, $this->retryDelayMs, throw: false)
                ->get($path);
        } catch (ConnectionException $exception) {
            throw SensorCommunityException::connectionFailed($url, $exception);
        }

        if ($response->failed()) {
            throw SensorCommunityException::requestFailed($url, $response->status());
        }

        $records = $response->json();

        if (! is_array($records)) {
            throw SensorCommunityException::invalidResponse($url, 'expected a JSON array');
        }

        $sensors = [];

        foreach ($records as $record) {
            if (! is_array($record)) {
                continue;
            }

            $sensor = $this->toSensorData($record);

            if ($sensor === null || $sensor->indoor || ! $this->measuresPm25($record)) {
                continue;
            }

            $sensors[$sensor->id] = $sensor;
        }

        return array_values($sensors);
    }

    /**
     * @param  array<string, mixed>  $record
     * @return SensorData|null
     */
    private function toSensorData(array $record): ?SensorData
    {
        $id = $record['sensor']['id'] ?? null;
        $type = $record['sensor']['sensor_type']['name'] ?? null;
        $latitude = $record['location']['latitude'] ?? null;
        $longitude = $record['location']['longitude'] ?? null;

        if ($id === null || $type === null || ! is_numeric($latitude) || ! is_numeric($longitude)) {
            return null;
        }

        return new SensorData(
            (int) $id,
            (string) $type,
            (float) $latitude,
            (float) $longitude,
            (bool) ($record['location']['indoor'] ?? false),
        );
    }

    /**
     * @param  array<string, mixed>  $record
     * @return bool
     */
    private function measuresPm25(array $record): bool
    {
        foreach ($record['sensordatavalues'] ?? [] as $value) {
            if (($value['value_type'] ?? null) === self::PM25_VALUE_TYPE) {
                return true;
            }
        }

        return false;
    }
}
