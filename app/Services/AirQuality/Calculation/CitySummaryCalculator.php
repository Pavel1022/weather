<?php

namespace App\Services\AirQuality\Calculation;

use App\Data\CitySummary;
use App\Data\SensorDayAverage;

final class CitySummaryCalculator
{
    /**
     * @param  list<SensorDayAverage>  $averages
     * @return array<string, CitySummary>
     */
    public function summarize(array $averages): array
    {
        $totals = [];

        foreach ($averages as $average) {
            $total = $totals[$average->city] ?? ['sum' => 0.0, 'days' => 0, 'sensors' => [], 'measurements' => 0];

            $total['sum'] += $average->average;
            $total['days']++;
            $total['sensors'][$average->sensorId] = true;
            $total['measurements'] += $average->count;

            $totals[$average->city] = $total;
        }

        $summaries = [];

        foreach ($totals as $city => $total) {
            $summaries[$city] = new CitySummary(
                $city,
                $total['sum'],
                $total['days'],
                count($total['sensors']),
                $total['measurements'],
            );
        }

        return $summaries;
    }
}
