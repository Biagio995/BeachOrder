<?php

namespace App\Services\Monitoring;

use Illuminate\Support\Facades\Cache;

class RequestMetrics
{
    /**
     * @return array{sample_count: int, error_rate: float, avg_response_time_ms: float, p95_response_time_ms: float}
     */
    public static function snapshot(): array
    {
        $samples = self::samples();
        $count = count($samples);

        if ($count === 0) {
            return [
                'sample_count' => 0,
                'error_rate' => 0.0,
                'avg_response_time_ms' => 0.0,
                'p95_response_time_ms' => 0.0,
            ];
        }

        $errors = 0;
        $durations = [];

        foreach ($samples as $sample) {
            if (($sample['status'] ?? 500) >= 500) {
                $errors++;
            }
            $durations[] = (float) ($sample['duration_ms'] ?? 0);
        }

        sort($durations);
        $p95Index = (int) ceil(0.95 * $count) - 1;

        return [
            'sample_count' => $count,
            'error_rate' => round($errors / $count, 4),
            'avg_response_time_ms' => round(array_sum($durations) / $count, 2),
            'p95_response_time_ms' => round($durations[max(0, $p95Index)], 2),
        ];
    }

    public static function record(int $status, float $durationMs, string $route, ?string $requestId = null): void
    {
        $window = (int) config('monitoring.metrics_window_seconds', 300);
        $maxSamples = (int) config('monitoring.metrics_max_samples', 500);

        $samples = self::samples();
        $samples[] = [
            'status' => $status,
            'duration_ms' => round($durationMs, 2),
            'route' => $route,
            'request_id' => $requestId,
            'at' => now()->toIso8601String(),
        ];

        $cutoff = now()->subSeconds($window)->getTimestamp();
        $samples = array_values(array_filter(
            $samples,
            fn (array $sample) => strtotime((string) ($sample['at'] ?? '')) >= $cutoff
        ));

        if (count($samples) > $maxSamples) {
            $samples = array_slice($samples, -$maxSamples);
        }

        Cache::put((string) config('monitoring.metrics_key'), $samples, now()->addSeconds($window + 60));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function samples(): array
    {
        $samples = Cache::get((string) config('monitoring.metrics_key'), []);

        return is_array($samples) ? $samples : [];
    }
}
