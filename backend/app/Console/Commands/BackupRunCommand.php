<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;

class BackupRunCommand extends Command
{
    protected $signature = 'backup:run';

    protected $description = 'Create an encrypted database backup and upload it to remote storage';

    public function handle(DatabaseBackupService $backupService): int
    {
        if (! config('backup.enabled')) {
            $this->warn('Backups are disabled (BACKUP_ENABLED=false).');

            return self::SUCCESS;
        }

        $this->info('Starting database backup...');

        $log = $backupService->run();

        $this->info("Backup completed: {$log->filename}");
        $this->line("Remote path: {$log->remote_path}");
        $this->line('Size: '.number_format((int) $log->size_bytes).' bytes');
        $this->line("Checksum: {$log->checksum}");

        return self::SUCCESS;
    }
}
