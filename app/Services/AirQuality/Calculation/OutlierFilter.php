<?php

namespace App\Services\AirQuality\Calculation;

use App\Data\SensorDayAverage;

final class OutlierFilter
{
    /**
     * @param  float  $maxRatioToMedian
     * @param  float  $minDifference
     * @param  int  $minSensors
     */
    public function __construct(
        private readonly float $maxRatioToMedian,
        private readonly float $minDifference,
        private readonly int $minSensors,
    ) {}

    /**
     * @param  list<SensorDayAverage>  $averages
     * @return list<SensorDayAverage>
     */
    public function withoutOutliers(array $averages): array
    {
        $kept = [];

        foreach ($this->groupByCityAndDay($averages) as $group) {
            if (count($group) < $this->minSensors) {
                array_push($kept, ...$group);

                continue;
            }

            $median = $this->median(array_map(fn (SensorDayAverage $average): float => $average->average, $group));

            foreach ($group as $average) {
                if (! $this->isOutlier($average->average, $median)) {
                    $kept[] = $average;
                }
            }
        }

        return $kept;
    }

    /**
     * @param  float  $value
     * @param  float  $median
     * @return bool
     */
    public function isOutlier(float $value, float $median): bool
    {
        return $value > $median * $this->maxRatioToMedian
            && $value - $median > $this->minDifference;
    }

    /**
     * @param  list<float>  $values
     * @return float
     */
    public function median(array $values): float
    {
        sort($values);
        $count = count($values);
        $middle = intdiv($count, 2);

        if ($count === 0) {
            return 0.0;
        }

        return $count % 2 === 1
            ? $values[$middle]
            : ($values[$middle - 1] + $values[$middle]) / 2;
    }

    /**
     * @param  list<SensorDayAverage>  $averages
     * @return array<string, list<SensorDayAverage>>
     */
    private function groupByCityAndDay(array $averages): array
    {
        $groups = [];

        foreach ($averages as $average) {
            $groups[$average->city.'|'.$average->date->toDateString()][] = $average;
        }

        return $groups;
    }
}
