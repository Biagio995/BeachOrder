<?php

namespace App\Console\Commands;

use App\Services\Monitoring\CriticalErrorAlerter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MonitorFailedJobs extends Command
{
    protected $signature = 'monitor:failed-jobs {--alert : Send alerts when threshold exceeded}';

    protected $description = 'Report failed queue jobs count';

    public function handle(): int
    {
        if (! Schema::hasTable('failed_jobs')) {
            $this->warn('failed_jobs table not found');

            return self::SUCCESS;
        }

        $count = (int) DB::table('failed_jobs')->count();
        $threshold = (int) config('monitoring.failed_jobs_alert_threshold', 5);

        $this->line("Failed jobs: {$count} (threshold: {$threshold})");

        if ($this->option('alert') && $count >= $threshold) {
            CriticalErrorAlerter::alert('failed_jobs_threshold', 'Failed jobs threshold exceeded', [
                'failed_jobs' => $count,
                'threshold' => $threshold,
            ]);
        }

        return $count >= $threshold ? self::FAILURE : self::SUCCESS;
    }
}
