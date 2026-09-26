<?php

namespace App\Services\Monitoring;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class HealthCheckService
{
    /**
     * @return array{status: string, timestamp: string, checks: array<string, array<string, mixed>>, metrics: array<string, mixed>}
     */
    public function run(): array
    {
        $checks = [
            'api' => $this->checkApi(),
            'database' => $this->checkDatabase(),
            'frontend' => $this->checkFrontend(),
            'background_jobs' => $this->checkBackgroundJobs(),
            'payment_webhooks' => WebhookMonitor::health(),
            'backup_jobs' => BackupMonitor::health(),
        ];

        $metrics = RequestMetrics::snapshot();
        $checks['error_rate'] = $this->checkErrorRate($metrics);
        $checks['response_time'] = $this->checkResponseTime($metrics);

        return [
            'status' => $this->aggregateStatus($checks),
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
            'metrics' => $metrics,
        ];
    }

    /**
     * @return array{status: string}
     */
    private function checkApi(): array
    {
        return ['status' => 'ok'];
    }

    /**
     * @return array{status: string, latency_ms: ?float, driver: string}
     */
    private function checkDatabase(): array
    {
        $driver = (string) config('database.default');

        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            DB::select('select 1 as ok');

            return [
                'status' => 'ok',
                'latency_ms' => round((microtime(true) - $start) * 1000, 2),
                'driver' => $driver,
            ];
        } catch (\Throwable) {
            return [
                'status' => 'fail',
                'latency_ms' => null,
                'driver' => $driver,
            ];
        }
    }

    /**
     * @return array{status: string, latency_ms: ?float, url: ?string}
     */
    private function checkFrontend(): array
    {
        if (! config('monitoring.frontend_check_enabled', true)) {
            return ['status' => 'skipped', 'latency_ms' => null, 'url' => null];
        }

        $url = config('monitoring.frontend_url') ?: config('app.frontend_url');
        if (! is_string($url) || $url === '') {
            return ['status' => 'skipped', 'latency_ms' => null, 'url' => null];
        }

        try {
            $start = microtime(true);
            $response = Http::timeout((int) config('monitoring.frontend_timeout_seconds', 3))
                ->get(rtrim($url, '/'));

            return [
                'status' => $response->successful() ? 'ok' : 'fail',
                'latency_ms' => round((microtime(true) - $start) * 1000, 2),
                'url' => $url,
            ];
        } catch (\Throwable) {
            return [
                'status' => 'fail',
                'latency_ms' => null,
                'url' => $url,
            ];
        }
    }

    /**
     * @return array{status: string, failed_jobs: int, pending_jobs: int, worker_alive: bool}
     */
    private function checkBackgroundJobs(): array
    {
        $failedJobs = 0;
        $pendingJobs = 0;

        try {
            if (Schema::hasTable('failed_jobs')) {
                $failedJobs = (int) DB::table('failed_jobs')->count();
            }
            if (Schema::hasTable('jobs')) {
                $pendingJobs = (int) DB::table('jobs')->count();
            }
        } catch (\Throwable) {
            return [
                'status' => 'fail',
                'failed_jobs' => $failedJobs,
                'pending_jobs' => $pendingJobs,
                'worker_alive' => false,
            ];
        }

        $heartbeat = Cache::get((string) config('monitoring.queue_heartbeat_key'));
        $maxAge = (int) config('monitoring.queue_heartbeat_max_age_seconds', 120);
        // Carbon 3 returns a signed diff (negative when $heartbeat is in the past).
        // Use the absolute age so staleness is independent of cache TTL.
        $workerAlive = is_string($heartbeat)
            && abs((float) now()->diffInSeconds($heartbeat)) <= $maxAge;

        $threshold = (int) config('monitoring.failed_jobs_alert_threshold', 5);
        $status = 'ok';
        if ($failedJobs >= $threshold) {
            $status = 'degraded';
        }
        if (! $workerAlive && $pendingJobs > 0) {
            $status = 'degraded';
        }

        return [
            'status' => $status,
            'failed_jobs' => $failedJobs,
            'pending_jobs' => $pendingJobs,
            'worker_alive' => $workerAlive,
        ];
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @return array{status: string, value: float, threshold: float, sample_count: int}
     */
    private function checkErrorRate(array $metrics): array
    {
        $value = (float) ($metrics['error_rate'] ?? 0);
        $threshold = (float) config('monitoring.error_rate_alert_threshold', 0.05);
        $samples = (int) ($metrics['sample_count'] ?? 0);

        return [
            'status' => $samples === 0 ? 'unknown' : ($value >= $threshold ? 'degraded' : 'ok'),
            'value' => $value,
            'threshold' => $threshold,
            'sample_count' => $samples,
        ];
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @return array{status: string, p95_ms: float, threshold_ms: int, sample_count: int}
     */
    private function checkResponseTime(array $metrics): array
    {
        $p95 = (float) ($metrics['p95_response_time_ms'] ?? 0);
        $threshold = (int) config('monitoring.response_time_alert_ms', 3000);
        $samples = (int) ($metrics['sample_count'] ?? 0);

        return [
            'status' => $samples === 0 ? 'unknown' : ($p95 >= $threshold ? 'degraded' : 'ok'),
            'p95_ms' => $p95,
            'threshold_ms' => $threshold,
            'sample_count' => $samples,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $checks
     */
    private function aggregateStatus(array $checks): string
    {
        $priorities = ['fail' => 3, 'stale' => 2, 'degraded' => 1, 'ok' => 0, 'unknown' => 0, 'skipped' => 0];
        $max = 0;

        foreach ($checks as $check) {
            $status = (string) ($check['status'] ?? 'unknown');
            $max = max($max, $priorities[$status] ?? 0);
        }

        return match ($max) {
            3 => 'unhealthy',
            2, 1 => 'degraded',
            default => 'healthy',
        };
    }
}
