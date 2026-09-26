<?php

namespace App\Services\Monitoring;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class QueueHeartbeat
{
    /**
     * Record that a queue worker is alive.
     *
     * Written by the worker process (WorkerStarting / Looping), not by the
     * scheduler — demo containers intentionally omit cron/schedule:run.
     *
     * Cache failures are logged and swallowed so a flaky cache store never
     * stops or crashes the queue worker.
     */
    public static function touch(): void
    {
        try {
            Cache::put(
                (string) config('monitoring.queue_heartbeat_key'),
                now()->toIso8601String(),
                now()->addMinutes(5)
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to write queue worker heartbeat', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
