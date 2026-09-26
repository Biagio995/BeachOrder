<?php

namespace App\Services;

use App\Models\BackupLog;
use App\Models\User;
use App\Notifications\BackupFailedNotification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class BackupAlertService
{
    public function alertFailure(BackupLog $backupLog, \Throwable $exception): void
    {
        Log::error('Database backup failed', [
            'backup_log_id' => $backupLog->id,
            'filename' => $backupLog->filename,
            'driver' => $backupLog->driver,
            'error' => $exception->getMessage(),
        ]);

        $this->sendEmailAlert($backupLog, $exception);
        $this->sendSlackAlert($backupLog, $exception);
    }

    protected function sendEmailAlert(BackupLog $backupLog, \Throwable $exception): void
    {
        $email = config('backup.alert_email') ?: config('mail.from.address');

        if (! $email) {
            return;
        }

        $notifiable = User::query()
            ->where('role', User::ROLE_SUPER_ADMIN)
            ->where('is_active', true)
            ->first();

        if ($notifiable) {
            Notification::send($notifiable, new BackupFailedNotification($backupLog, $exception));

            return;
        }

        Notification::route('mail', $email)
            ->notify(new BackupFailedNotification($backupLog, $exception));
    }

    protected function sendSlackAlert(BackupLog $backupLog, \Throwable $exception): void
    {
        $webhook = config('backup.alert_slack_webhook');

        if (! $webhook) {
            return;
        }

        Http::timeout(10)->post($webhook, [
            'text' => sprintf(
                ':x: %s backup failed (%s): %s',
                (string) config('app.name'),
                $backupLog->filename ?: 'unknown',
                $exception->getMessage()
            ),
        ]);
    }
}
