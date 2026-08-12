<?php

namespace App\Http\Middleware;

use App\Services\Monitoring\RequestMetrics;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogRequestMetrics
{
    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        /** @var Response $response */
        $response = $next($request);

        if ($this->shouldSkip($request)) {
            return $response;
        }

        $durationMs = (microtime(true) - $start) * 1000;
        $route = $request->route()?->getName() ?? $request->path();
        $requestId = $request->headers->get('X-Request-Id');
        $status = $response->getStatusCode();

        RequestMetrics::record($status, $durationMs, (string) $route, is_string($requestId) ? $requestId : null);

        Log::channel('app')->info('request.completed', [
            'request_id' => $requestId,
            'method' => $request->method(),
            'route' => $route,
            'status' => $status,
            'duration_ms' => round($durationMs, 2),
        ]);

        return $response;
    }

    private function shouldSkip(Request $request): bool
    {
        return $request->is('up')
            || $request->is('api/health')
            || $request->is('health');
    }
}
