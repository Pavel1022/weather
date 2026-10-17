<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CleanestCityRequest;
use App\Http\Resources\RankingResource;
use App\Services\AirQuality\Contracts\CleanAirRankingInterface;
use Illuminate\Support\Facades\Log;

final class CleanestCityController extends Controller
{
    /**
     * @param  CleanestCityRequest  $request
     * @param  CleanAirRankingInterface  $ranking
     * @return RankingResource
     */
    public function __invoke(CleanestCityRequest $request, CleanAirRankingInterface $ranking): RankingResource
    {
        $startedAt = microtime(true);
        $result = $ranking->rank($request->period());

        Log::info('Ranking requested', [
            'from' => $result->period->from->toDateString(),
            'to' => $result->period->to->toDateString(),
            'cleanest' => $result->cleanest()?->city,
            'ms' => round((microtime(true) - $startedAt) * 1000, 1),
        ]);

        return new RankingResource($result);
    }
}
