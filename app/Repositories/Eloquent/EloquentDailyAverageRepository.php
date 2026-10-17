<?php

namespace App\Repositories\Eloquent;

use App\Data\DateRange;
use App\Data\SensorDayAverage;
use App\Models\SensorDailyAverage;
use App\Repositories\Contracts\DailyAverageRepositoryInterface;
use Carbon\CarbonImmutable;

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
     * @return list<SensorDayAverage>
     */
    public function sensorDaysBetween(DateRange $range): array
    {
        $rows = SensorDailyAverage::query()
            ->toBase()
            ->select(['sensor_id', 'city', 'date', 'pm25_sum', 'pm25_count', 'pm25_avg'])
            ->whereBetween('date', [$range->from->toDateString(), $range->to->toDateString()])
            ->orderBy('date')
            ->orderBy('sensor_id')
            ->get();

        $dates = [];
        $averages = [];

        foreach ($rows as $row) {
            $day = substr((string) $row->date, 0, 10);
            $dates[$day] ??= CarbonImmutable::createFromFormat('!Y-m-d', $day);

            $averages[] = new SensorDayAverage(
                (int) $row->sensor_id,
                $row->city,
                $dates[$day],
                (float) $row->pm25_sum,
                (int) $row->pm25_count,
                (float) $row->pm25_avg,
            );
        }

        return $averages;
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
