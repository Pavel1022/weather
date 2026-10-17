<?php

namespace App\Http\Resources;

use App\Data\CityAverage;
use App\Data\Ranking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Ranking $resource
 */
final class RankingResource extends JsonResource
{
    /**
     * @var string|null
     */
    public static $wrap = null;

    /**
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'from' => $this->resource->period->from->toDateString(),
            'to' => $this->resource->period->to->toDateString(),
            'ranking' => array_map(fn (CityAverage $city): array => [
                'city' => $city->city,
                'avg_pm25' => $city->averagePm25,
                'sensors' => $city->sensors,
                'measurements' => $city->measurements,
            ], $this->resource->cities),
        ];
    }
}
