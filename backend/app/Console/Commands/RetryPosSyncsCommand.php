<?php

namespace App\Console\Commands;

use App\Services\Pos\PosOrderSyncService;
use Illuminate\Console\Command;

class RetryPosSyncsCommand extends Command
{
    protected $signature = 'pos:retry-syncs';

    protected $description = 'Dispatch retries for failed POS order synchronizations';

    public function handle(PosOrderSyncService $syncService): int
    {
        $count = $syncService->scheduleRetries();
        $this->info("Queued {$count} POS sync retries.");

        return self::SUCCESS;
    }
}
