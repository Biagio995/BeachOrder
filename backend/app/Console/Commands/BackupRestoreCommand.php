<?php

namespace App\Console\Commands;

use App\Models\BackupLog;
use App\Services\DatabaseRestoreService;
use Illuminate\Console\Command;

class BackupRestoreCommand extends Command
{
    protected $signature = 'backup:restore
                            {backup? : Backup log ID or remote filename}
                            {--latest : Restore the most recent completed backup}
                            {--force : Allow restore outside local/testing environments}';

    protected $description = 'Restore the database from a remote backup artifact';

    public function handle(DatabaseRestoreService $restoreService): int
    {
        $backupLog = $this->resolveBackupLog();

        if (! $backupLog) {
            $this->error('No backup found to restore.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm(
            "This will overwrite the current [{$backupLog->driver}] database. Continue?"
        )) {
            $this->warn('Restore cancelled.');

            return self::SUCCESS;
        }

        $this->info("Restoring backup #{$backupLog->id} ({$backupLog->filename})...");

        $restoreService->restore($backupLog, (bool) $this->option('force'));

        $this->info('Database restore completed successfully.');

        return self::SUCCESS;
    }

    protected function resolveBackupLog(): ?BackupLog
    {
        if ($this->option('latest')) {
            return BackupLog::query()
                ->where('status', BackupLog::STATUS_COMPLETED)
                ->latest('id')
                ->first();
        }

        $reference = $this->argument('backup');

        if (! $reference) {
            return BackupLog::query()
                ->where('status', BackupLog::STATUS_COMPLETED)
                ->latest('id')
                ->first();
        }

        if (is_numeric($reference)) {
            return BackupLog::query()->find((int) $reference);
        }

        return BackupLog::query()
            ->where('filename', $reference)
            ->orWhere('remote_path', 'like', '%'.$reference)
            ->where('status', BackupLog::STATUS_COMPLETED)
            ->latest('id')
            ->first();
    }
}
