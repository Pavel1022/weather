<?php

namespace App\Providers;

use App\Repositories\Contracts\DailyAverageRepositoryInterface;
use App\Repositories\Contracts\ImportRunRepositoryInterface;
use App\Repositories\Contracts\SensorRepositoryInterface;
use App\Repositories\Eloquent\EloquentDailyAverageRepository;
use App\Repositories\Eloquent\EloquentImportRunRepository;
use App\Repositories\Eloquent\EloquentSensorRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * @return void
     */
    public function register(): void
    {
        $this->app->bind(SensorRepositoryInterface::class, EloquentSensorRepository::class);
        $this->app->bind(DailyAverageRepositoryInterface::class, EloquentDailyAverageRepository::class);
        $this->app->bind(ImportRunRepositoryInterface::class, EloquentImportRunRepository::class);
    }

    /**
     * @return void
     */
    public function boot(): void {}
}
