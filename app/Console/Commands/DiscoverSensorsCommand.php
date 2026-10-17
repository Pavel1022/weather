<?php

namespace App\Console\Commands;

use App\Services\AirQuality\SensorDiscoveryService;
use App\Services\Api\SensorCommunity\Exceptions\SensorCommunityException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('air:discover')]
#[Description('Discover outdoor particulate matter sensors around the configured cities')]
class DiscoverSensorsCommand extends Command
{
    /**
     * @param  SensorDiscoveryService  $discovery
     * @return int
     */
    public function handle(SensorDiscoveryService $discovery): int
    {
        try {
            $counts = $discovery->discover();
        } catch (SensorCommunityException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['City', 'Sensors'],
            array_map(fn (string $city, int $count): array => [$city, $count], array_keys($counts), $counts),
        );
        $this->info('Total sensors: '.array_sum($counts));

        return self::SUCCESS;
    }
}
