<?php

namespace App\Console\Commands;

use App\Data\DateRange;
use App\Services\AirQuality\DayImportService;
use App\Services\Api\SensorCommunity\Exceptions\SensorCommunityException;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriodImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;

#[Signature('air:import
    {--days= : Import the last N days, ending yesterday}
    {--from= : Start date YYYY-MM-DD}
    {--to= : End date YYYY-MM-DD}
    {--force : Re-import days that are already completed}')]
#[Description('Download Sensor.Community archive data and store daily PM2.5 averages')]
class ImportAirQualityCommand extends Command
{
    /**
     * @param  DayImportService  $importer
     * @return int
     */
    public function handle(DayImportService $importer): int
    {
        try {
            $period = $this->period();
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $days = iterator_to_array(CarbonPeriodImmutable::create($period->from, $period->to), false);
        $force = (bool) $this->option('force');
        $imported = 0;
        $skipped = 0;
        $failed = [];
        $readings = 0;
        $sensorDays = 0;
        $pauseMs = (int) config('air.sensor_community.day_pause_ms', 0);
        $downloaded = false;

        $this->info("Importing {$period->from->toDateString()} to {$period->to->toDateString()} (".count($days).' days)');

        $this->withProgressBar($days, function (CarbonImmutable $day) use ($importer, $force, $pauseMs, &$downloaded, &$imported, &$skipped, &$failed, &$readings, &$sensorDays): void {
            if ($downloaded && $pauseMs > 0) {
                usleep($pauseMs * 1000);
            }

            $downloaded = false;

            try {
                $result = $importer->import($day, $force);
            } catch (SensorCommunityException $exception) {
                $downloaded = true;
                $failed[$day->toDateString()] = $exception->getMessage();

                return;
            }

            if ($result->skipped) {
                $skipped++;

                return;
            }

            $downloaded = true;
            $imported++;
            $readings += $result->readings;
            $sensorDays += $result->sensorDays;
        });

        $this->newLine(2);
        $this->table(['Imported', 'Skipped', 'Failed', 'Readings', 'Sensor days'], [[$imported, $skipped, count($failed), $readings, $sensorDays]]);

        foreach ($failed as $day => $message) {
            $this->warn("{$day}: {$message}");
        }

        return $failed === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return DateRange
     *
     * @throws InvalidArgumentException
     */
    private function period(): DateRange
    {
        $from = $this->option('from');
        $to = $this->option('to');

        if ($from !== null || $to !== null) {
            if ($from === null || $to === null) {
                throw new InvalidArgumentException('Use --from and --to together.');
            }

            if (! $this->isDate($from) || ! $this->isDate($to)) {
                throw new InvalidArgumentException('Dates must be in the YYYY-MM-DD format.');
            }

            return DateRange::fromStrings($from, $to);
        }

        $days = $this->option('days') ?? config('air.history_days');

        if (! is_numeric($days) || (int) $days < 1) {
            throw new InvalidArgumentException('--days must be a positive number.');
        }

        $yesterday = CarbonImmutable::yesterday();

        return new DateRange($yesterday->subDays((int) $days - 1), $yesterday);
    }

    /**
     * @param  string  $value
     * @return bool
     */
    private function isDate(string $value): bool
    {
        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);
        } catch (InvalidArgumentException) {
            return false;
        }

        return $date !== null && $date->format('Y-m-d') === $value;
    }
}
