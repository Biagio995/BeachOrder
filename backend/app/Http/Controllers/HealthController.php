<?php

namespace App\Http\Controllers;

use App\Services\Monitoring\HealthCheckService;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __invoke(HealthCheckService $health): JsonResponse
    {
        $report = $health->run();

        $httpStatus = match ($report['status']) {
            'unhealthy' => 503,
            'degraded' => 200,
            default => 200,
        };

        return response()->json($report, $httpStatus);
    }
}
