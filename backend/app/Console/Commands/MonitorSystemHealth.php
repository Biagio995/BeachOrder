<?php

namespace App\Console\Commands;

use App\Services\Monitoring\CriticalErrorAlerter;
use App\Services\Monitoring\HealthCheckService;
use Illuminate\Console\Command;

class MonitorSystemHealth extends Command
{
    protected $signature = 'monitor:health {--alert : Send alerts when unhealthy}';

    protected $description = 'Run health checks and optionally alert on failures';

    public function handle(HealthCheckService $health): int
    {
        // Queue heartbeat is owned by the worker process (WorkerStarting /
        // Looping). Do not fake it here — that made worker_alive:true even
        // when no queue:work was running (and demo has no scheduler anyway).

        $report = $health->run();

        $this->line('Status: '.$report['status']);

        foreach ($report['checks'] as $name => $check) {
            $this->line(sprintf('  - %s: %s', $name, $check['status'] ?? 'unknown'));
        }

        if ($this->option('alert')) {
            CriticalErrorAlerter::alertIfUnhealthy($report);

            $failedJobs = (int) ($report['checks']['background_jobs']['failed_jobs'] ?? 0);
            $threshold = (int) config('monitoring.failed_jobs_alert_threshold', 5);

            if ($failedJobs >= $threshold) {
                CriticalErrorAlerter::alert('failed_jobs_threshold', 'Failed jobs threshold exceeded', [
                    'failed_jobs' => $failedJobs,
                    'threshold' => $threshold,
                ]);
            }
        }

        return ($report['status'] ?? '') === 'unhealthy' ? self::FAILURE : self::SUCCESS;
    }
}
