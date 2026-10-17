<?php

namespace App\Services\Api\SensorCommunity;

use App\Services\Api\SensorCommunity\Contracts\SensorArchiveInterface;
use App\Services\Api\SensorCommunity\Exceptions\SensorCommunityException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class SensorCommunityArchiveClient implements SensorArchiveInterface
{
    /**
     * @var string
     */
    private const CSV_LINK_PATTERN = '/href="([^"?\/]+\.csv)"/';

    /**
     * @param  string  $baseUrl
     * @param  int  $timeout
     * @param  int  $retries
     * @param  int  $retryDelayMs
     * @param  int  $concurrency
     */
    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeout,
        private readonly int $retries,
        private readonly int $retryDelayMs,
        private readonly int $concurrency,
    ) {}

    /**
     * @param  CarbonImmutable  $date
     * @return list<string>
     *
     * @throws SensorCommunityException
     */
    public function filesForDate(CarbonImmutable $date): array
    {
        $path = $this->dayPath($date);
        $url = rtrim($this->baseUrl, '/').$path;

        try {
            $response = Http::baseUrl($this->baseUrl)
                ->timeout($this->timeout)
                ->retry($this->retries, $this->retryDelayMs, throw: false)
                ->get($path);
        } catch (ConnectionException $exception) {
            throw SensorCommunityException::connectionFailed($url, $exception);
        }

        if ($response->notFound()) {
            return [];
        }

        if ($response->failed()) {
            throw SensorCommunityException::requestFailed($url, $response->status());
        }

        preg_match_all(self::CSV_LINK_PATTERN, $response->body(), $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * @param  CarbonImmutable  $date
     * @param  list<string>  $fileNames
     * @return array<string, string|null>
     */
    public function downloadMany(CarbonImmutable $date, array $fileNames): array
    {
        $results = [];
        $path = $this->dayPath($date);

        foreach (array_chunk($fileNames, max(1, $this->concurrency)) as $chunk) {
            $responses = Http::pool(fn (Pool $pool): array => array_map(
                fn (string $name) => $pool->as($name)
                    ->baseUrl($this->baseUrl)
                    ->timeout($this->timeout)
                    ->get($path.$name),
                $chunk,
            ));

            foreach ($chunk as $name) {
                $response = $responses[$name] ?? null;

                $results[$name] = $response instanceof Response && $response->successful()
                    ? $response->body()
                    : null;
            }
        }

        return $results;
    }

    /**
     * @param  CarbonImmutable  $date
     * @return string
     */
    private function dayPath(CarbonImmutable $date): string
    {
        return '/'.$date->toDateString().'/';
    }
}
