<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CleanestCityRequest;
use App\Http\Resources\RankingResource;
use App\Services\AirQuality\CleanAirRankingService;
use Illuminate\Support\Facades\Log;

final class CleanestCityController extends Controller
{
    /**
     * @param  CleanestCityRequest  $request
     * @param  CleanAirRankingService  $ranking
     * @return RankingResource
     */
    public function __invoke(CleanestCityRequest $request, CleanAirRankingService $ranking): RankingResource
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
