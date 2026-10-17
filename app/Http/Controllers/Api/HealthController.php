<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\ImportRunRepositoryInterface;
use App\Repositories\Contracts\SensorRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class HealthController extends Controller
{
    /**
     * @param  SensorRepositoryInterface  $sensors
     * @param  ImportRunRepositoryInterface  $runs
     * @return JsonResponse
     */
    public function __invoke(SensorRepositoryInterface $sensors, ImportRunRepositoryInterface $runs): JsonResponse
    {
        try {
            DB::select('select 1');

            return response()->json([
                'status' => 'ok',
                'database' => 'ok',
                'sensors' => $sensors->count(),
                'last_import' => $runs->lastCompletedDate()?->toDateString(),
            ]);
        } catch (Throwable $exception) {
            Log::error('Health check failed', ['error' => $exception->getMessage()]);

            return response()->json([
                'status' => 'degraded',
                'database' => 'down',
                'sensors' => null,
                'last_import' => null,
            ], 503);
        }
    }
}
