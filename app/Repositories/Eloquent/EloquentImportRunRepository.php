<?php

namespace App\Repositories\Eloquent;

use App\Data\ImportResult;
use App\Models\ImportRun;
use App\Repositories\Contracts\ImportRunRepositoryInterface;
use Carbon\CarbonImmutable;

final class EloquentImportRunRepository implements ImportRunRepositoryInterface
{
    /**
     * @param  CarbonImmutable  $date
     * @return bool
     */
    public function isCompleted(CarbonImmutable $date): bool
    {
        return ImportRun::query()
            ->whereDate('date', $date->toDateString())
            ->where('status', ImportRun::STATUS_COMPLETED)
            ->exists();
    }

    /**
     * @param  CarbonImmutable  $date
     * @return void
     */
    public function markStarted(CarbonImmutable $date): void
    {
        $this->runFor($date)->fill([
            'status' => ImportRun::STATUS_RUNNING,
            'error' => null,
            'finished_at' => null,
        ])->save();
    }

    /**
     * @param  ImportResult  $result
     * @return void
     */
    public function markCompleted(ImportResult $result): void
    {
        $this->runFor($result->date)->fill([
            'status' => ImportRun::STATUS_COMPLETED,
            'files' => $result->files,
            'failed_files' => $result->failedFiles,
            'readings' => $result->readings,
            'rejected_readings' => $result->rejectedReadings,
            'sensor_days' => $result->sensorDays,
            'error' => null,
            'finished_at' => CarbonImmutable::now(),
        ])->save();
    }

    /**
     * @param  CarbonImmutable  $date
     * @param  string  $error
     * @return void
     */
    public function markFailed(CarbonImmutable $date, string $error): void
    {
        $this->runFor($date)->fill([
            'status' => ImportRun::STATUS_FAILED,
            'error' => $error,
            'finished_at' => CarbonImmutable::now(),
        ])->save();
    }

    /**
     * @return CarbonImmutable|null
     */
    public function lastCompletedDate(): ?CarbonImmutable
    {
        $date = ImportRun::query()
            ->where('status', ImportRun::STATUS_COMPLETED)
            ->max('date');

        return $date === null ? null : CarbonImmutable::parse($date)->startOfDay();
    }

    /**
     * @param  CarbonImmutable  $date
     * @return ImportRun
     */
    private function runFor(CarbonImmutable $date): ImportRun
    {
        return ImportRun::query()->whereDate('date', $date->toDateString())->first()
            ?? new ImportRun(['date' => $date->startOfDay()]);
    }
}
