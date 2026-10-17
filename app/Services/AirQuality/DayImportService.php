<?php

namespace App\Services\AirQuality;

use App\Data\ArchiveFile;
use App\Data\ImportResult;
use App\Events\DayImported;
use App\Repositories\Contracts\DailyAverageRepositoryInterface;
use App\Repositories\Contracts\ImportRunRepositoryInterface;
use App\Repositories\Contracts\SensorRepositoryInterface;
use App\Services\AirQuality\Calculation\SensorDayAverager;
use App\Services\Api\SensorCommunity\ArchiveCsvParser;
use App\Services\Api\SensorCommunity\ArchiveFileNameParser;
use App\Services\Api\SensorCommunity\Contracts\SensorArchiveInterface;
use App\Services\Api\SensorCommunity\Exceptions\SensorCommunityException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

final class DayImportService
{
    /**
     * @param  SensorArchiveInterface  $archive
     * @param  ArchiveFileNameParser  $fileNames
     * @param  ArchiveCsvParser  $csv
     * @param  SensorDayAverager  $averager
     * @param  SensorRepositoryInterface  $sensors
     * @param  DailyAverageRepositoryInterface  $averages
     * @param  ImportRunRepositoryInterface  $runs
     */
    public function __construct(
        private readonly SensorArchiveInterface $archive,
        private readonly ArchiveFileNameParser $fileNames,
        private readonly ArchiveCsvParser $csv,
        private readonly SensorDayAverager $averager,
        private readonly SensorRepositoryInterface $sensors,
        private readonly DailyAverageRepositoryInterface $averages,
        private readonly ImportRunRepositoryInterface $runs,
    ) {}

    /**
     * @param  CarbonImmutable  $date
     * @param  bool  $force
     * @return ImportResult
     *
     * @throws SensorCommunityException
     */
    public function import(CarbonImmutable $date, bool $force = false): ImportResult
    {
        $date = $date->startOfDay();

        if (! $force && $this->runs->isCompleted($date)) {
            return new ImportResult($date, 0, 0, 0, 0, 0, true);
        }

        $this->runs->markStarted($date);
        Log::channel('import')->info('Import started', ['date' => $date->toDateString()]);
        $startedAt = microtime(true);

        try {
            $citiesBySensorId = $this->sensors->citiesBySensorId();
            $files = $this->relevantFiles($date, $citiesBySensorId);
            $contents = $this->archive->downloadMany($date, array_keys($files));

            $dayAverages = [];
            $readings = 0;
            $rejected = 0;
            $failed = 0;

            foreach ($contents as $name => $csvText) {
                if ($csvText === null) {
                    $failed++;
                    Log::channel('import')->warning('Download failed', ['date' => $date->toDateString(), 'file' => $name]);

                    continue;
                }

                $file = $files[$name];
                $parsed = $this->csv->parse($csvText);
                $readings += count($parsed);

                $average = $this->averager->average($file->sensorId, $citiesBySensorId[$file->sensorId], $date, $parsed);
                $rejected += $this->averager->lastRejectedCount();

                if ($average !== null) {
                    $dayAverages[] = $average;
                }
            }

            $this->averages->saveMany($dayAverages);

            $result = new ImportResult($date, count($files), $failed, $readings, $rejected, count($dayAverages), false);
            $this->runs->markCompleted($result);
            DayImported::dispatch($result);

            Log::channel('import')->info('Import finished', [
                'date' => $date->toDateString(),
                'files' => $result->files,
                'failed_files' => $result->failedFiles,
                'readings' => $result->readings,
                'rejected_readings' => $result->rejectedReadings,
                'sensor_days' => $result->sensorDays,
                'seconds' => round(microtime(true) - $startedAt, 1),
            ]);

            return $result;
        } catch (SensorCommunityException $exception) {
            $this->runs->markFailed($date, $exception->getMessage());
            Log::channel('import')->error('Import failed', ['date' => $date->toDateString(), 'error' => $exception->getMessage()]);

            throw $exception;
        }
    }

    /**
     * @param  CarbonImmutable  $date
     * @param  array<int, string>  $citiesBySensorId
     * @return array<string, ArchiveFile>
     *
     * @throws SensorCommunityException
     */
    private function relevantFiles(CarbonImmutable $date, array $citiesBySensorId): array
    {
        $names = $this->archive->filesForDate($date);

        if ($names === []) {
            throw SensorCommunityException::dayNotAvailable($date->toDateString());
        }

        $files = [];

        foreach ($names as $name) {
            $file = $this->fileNames->parse($name);

            if ($file === null || $file->indoor || ! isset($citiesBySensorId[$file->sensorId])) {
                continue;
            }

            $files[$name] = $file;
        }

        return $files;
    }
}
