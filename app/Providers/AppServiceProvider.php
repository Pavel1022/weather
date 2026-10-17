<?php

namespace App\Providers;

use App\Repositories\Contracts\DailyAverageRepositoryInterface;
use App\Repositories\Contracts\ImportRunRepositoryInterface;
use App\Repositories\Contracts\SensorRepositoryInterface;
use App\Repositories\Eloquent\EloquentDailyAverageRepository;
use App\Repositories\Eloquent\EloquentImportRunRepository;
use App\Repositories\Eloquent\EloquentSensorRepository;
use App\Services\AirQuality\Calculation\CityResolver;
use App\Services\AirQuality\Calculation\OutlierFilter;
use App\Services\AirQuality\Calculation\PmReadingFilter;
use App\Services\AirQuality\Calculation\SensorDayAverager;
use App\Services\AirQuality\CityCatalog;
use App\Services\AirQuality\SensorDiscoveryService;
use App\Services\Api\SensorCommunity\Contracts\SensorArchiveInterface;
use App\Services\Api\SensorCommunity\Contracts\SensorDirectoryInterface;
use App\Services\Api\SensorCommunity\SensorCommunityApiClient;
use App\Services\Api\SensorCommunity\SensorCommunityArchiveClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * @return void
     */
    public function register(): void
    {
        $this->registerRepositories();
        $this->registerSensorCommunity();
        $this->registerAirQuality();
    }

    /**
     * @return void
     */
    public function boot(): void {}

    /**
     * @return void
     */
    private function registerRepositories(): void
    {
        $this->app->bind(SensorRepositoryInterface::class, EloquentSensorRepository::class);
        $this->app->bind(DailyAverageRepositoryInterface::class, EloquentDailyAverageRepository::class);
        $this->app->bind(ImportRunRepositoryInterface::class, EloquentImportRunRepository::class);
    }

    /**
     * @return void
     */
    private function registerSensorCommunity(): void
    {
        $clients = [SensorCommunityApiClient::class, SensorCommunityArchiveClient::class];

        $this->app->bind(SensorDirectoryInterface::class, SensorCommunityApiClient::class);
        $this->app->bind(SensorArchiveInterface::class, SensorCommunityArchiveClient::class);

        $this->app->when(SensorCommunityApiClient::class)->needs('$baseUrl')->giveConfig('air.sensor_community.api_url');
        $this->app->when(SensorCommunityArchiveClient::class)->needs('$baseUrl')->giveConfig('air.sensor_community.archive_url');
        $this->app->when($clients)->needs('$timeout')->giveConfig('air.sensor_community.timeout');
        $this->app->when($clients)->needs('$retries')->giveConfig('air.sensor_community.retries');
        $this->app->when($clients)->needs('$retryDelayMs')->giveConfig('air.sensor_community.retry_delay_ms');
        $this->app->when(SensorCommunityArchiveClient::class)->needs('$concurrency')->giveConfig('air.sensor_community.concurrency');
        $this->app->when(SensorCommunityArchiveClient::class)->needs('$batchPauseMs')->giveConfig('air.sensor_community.batch_pause_ms');
    }

    /**
     * @return void
     */
    private function registerAirQuality(): void
    {
        $this->app->when(CityCatalog::class)->needs('$cities')->giveConfig('air.cities');
        $this->app->when([CityResolver::class, SensorDiscoveryService::class])->needs('$radiusKm')->giveConfig('air.radius_km');
        $this->app->when(PmReadingFilter::class)->needs('$minimum')->giveConfig('air.min_valid_pm25');
        $this->app->when(PmReadingFilter::class)->needs('$maximum')->giveConfig('air.max_valid_pm25');
        $this->app->when(SensorDayAverager::class)->needs('$minimumReadings')->giveConfig('air.min_readings_per_day');
        $this->app->when(OutlierFilter::class)->needs('$maxRatioToMedian')->giveConfig('air.outliers.max_ratio_to_median');
        $this->app->when(OutlierFilter::class)->needs('$minDifference')->giveConfig('air.outliers.min_difference');
        $this->app->when(OutlierFilter::class)->needs('$minSensors')->giveConfig('air.outliers.min_sensors');
    }
}
