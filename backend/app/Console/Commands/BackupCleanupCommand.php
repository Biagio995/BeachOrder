<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;

class BackupCleanupCommand extends Command
{
    protected $signature = 'backup:cleanup';

    protected $description = 'Delete backups older than the configured retention period';

    public function handle(DatabaseBackupService $backupService): int
    {
        $deleted = $backupService->cleanupExpired();

        $this->info("Removed {$deleted} expired backup(s).");

        return self::SUCCESS;
    }
}
