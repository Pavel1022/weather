<?php

namespace App\Repositories\Eloquent;

use App\Data\CitySummary;
use App\Data\DateRange;
use App\Data\SensorDayAverage;
use App\Models\SensorDailyAverage;
use App\Repositories\Contracts\DailyAverageRepositoryInterface;

final class EloquentDailyAverageRepository implements DailyAverageRepositoryInterface
{
    /**
     * @var int
     */
    private const CHUNK_SIZE = 500;

    /**
     * @param  list<SensorDayAverage>  $averages
     * @return void
     */
    public function saveMany(array $averages): void
    {
        if ($averages === []) {
            return;
        }

        $rows = array_map(fn (SensorDayAverage $average): array => [
            'sensor_id' => $average->sensorId,
            'city' => $average->city,
            'date' => $average->date->toDateString(),
            'pm25_sum' => $average->sum,
            'pm25_count' => $average->count,
            'pm25_avg' => $average->average,
        ], $averages);

        foreach (array_chunk($rows, self::CHUNK_SIZE) as $chunk) {
            SensorDailyAverage::query()->upsert(
                $chunk,
                ['sensor_id', 'date'],
                ['city', 'pm25_sum', 'pm25_count', 'pm25_avg'],
            );
        }
    }

    /**
     * @param  DateRange  $range
     * @return array<string, CitySummary>
     */
    public function summariesBetween(DateRange $range): array
    {
        $rows = SensorDailyAverage::query()
            ->toBase()
            ->selectRaw('city, SUM(pm25_avg) AS sum_of_averages, COUNT(*) AS sensor_days, COUNT(DISTINCT sensor_id) AS sensors, SUM(pm25_count) AS measurements')
            ->whereBetween('date', [$range->from->toDateString(), $range->to->toDateString()])
            ->groupBy('city')
            ->get();

        $summaries = [];

        foreach ($rows as $row) {
            $summaries[$row->city] = new CitySummary(
                $row->city,
                (float) $row->sum_of_averages,
                (int) $row->sensor_days,
                (int) $row->sensors,
                (int) $row->measurements,
            );
        }

        return $summaries;
    }

    /**
     * @return DateRange|null
     */
    public function availableRange(): ?DateRange
    {
        $bounds = SensorDailyAverage::query()
            ->toBase()
            ->selectRaw('MIN(date) AS first_date, MAX(date) AS last_date')
            ->first();

        if ($bounds === null || $bounds->first_date === null) {
            return null;
        }

        return DateRange::fromStrings(
            substr((string) $bounds->first_date, 0, 10),
            substr((string) $bounds->last_date, 0, 10),
        );
    }
}
