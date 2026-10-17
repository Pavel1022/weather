<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\DailyAverageRepositoryInterface;
use Illuminate\Http\JsonResponse;

final class HistoryController extends Controller
{
    /**
     * @param  DailyAverageRepositoryInterface  $averages
     * @return JsonResponse
     */
    public function __invoke(DailyAverageRepositoryInterface $averages): JsonResponse
    {
        $range = $averages->availableRange();

        if ($range === null) {
            return response()->json(['message' => 'No air quality data has been imported yet.'], 404);
        }

        return response()->json([
            'from' => $range->from->toDateString(),
            'to' => $range->to->toDateString(),
        ]);
    }
}
