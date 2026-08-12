<?php

namespace App\Console\Commands;

use App\Models\BackupLog;
use Illuminate\Console\Command;

class BackupListCommand extends Command
{
    protected $signature = 'backup:list {--limit=20 : Number of recent backups to show}';

    protected $description = 'List recent database backups';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $backups = BackupLog::query()
            ->latest('id')
            ->limit($limit)
            ->get();

        if ($backups->isEmpty()) {
            $this->warn('No backups found.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Status', 'Driver', 'Filename', 'Size', 'Remote', 'Completed'],
            $backups->map(fn (BackupLog $log) => [
                $log->id,
                $log->status,
                $log->driver,
                $log->filename,
                $log->size_bytes ? number_format((int) $log->size_bytes).' B' : '-',
                $log->remote_path ?: '-',
                optional($log->completed_at)?->toDateTimeString() ?: '-',
            ])
        );

        return self::SUCCESS;
    }
}
