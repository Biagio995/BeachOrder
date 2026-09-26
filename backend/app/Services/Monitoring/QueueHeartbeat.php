<?php

namespace App\Services\Monitoring;

use Illuminate\Support\Facades\Cache;

class QueueHeartbeat
{
    /**
     * Record that a queue worker is alive.
     *
     * Written by the worker process (WorkerStarting / Looping), not by the
     * scheduler — demo containers intentionally omit cron/schedule:run.
     */
    public static function touch(): void
    {
        Cache::put(
            (string) config('monitoring.queue_heartbeat_key'),
            now()->toIso8601String(),
            now()->addMinutes(5)
        );
    }
}
