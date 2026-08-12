<?php

namespace App\Services\Monitoring;

use App\Models\BackupLog;
use Illuminate\Support\Facades\Log;

class BackupMonitor
{
    public static function recordSuccess(string $path, int $bytes): void
    {
        Log::channel('jobs')->info('backup.success', [
            'path' => basename($path),
            'bytes' => $bytes,
        ]);
    }

    public static function recordFailure(string $reason): void
    {
        Log::channel('jobs')->error('backup.failure', [
            'reason' => $reason,
        ]);

        CriticalErrorAlerter::alert('backup_failure', 'Database backup failed', [
            'reason' => $reason,
        ]);
    }

    /**
     * @return array{status: string, last_backup_at: ?string, bytes: ?int}
     */
    public static function health(): array
    {
        $staleAfter = (int) config('monitoring.backup_stale_after_seconds', 90000);

        $last = BackupLog::query()
            ->where('status', BackupLog::STATUS_COMPLETED)
            ->orderByDesc('completed_at')
            ->first();

        if ($last === null || $last->completed_at === null) {
            return [
                'status' => 'unknown',
                'last_backup_at' => null,
                'bytes' => null,
            ];
        }

        $age = now()->diffInSeconds($last->completed_at);

        return [
            'status' => $age <= $staleAfter ? 'ok' : 'stale',
            'last_backup_at' => $last->completed_at->toIso8601String(),
            'bytes' => $last->size_bytes,
        ];
    }
}
