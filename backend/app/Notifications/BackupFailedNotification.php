<?php

namespace App\Notifications;

use App\Models\BackupLog;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BackupFailedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public BackupLog $backupLog,
        public \Throwable $exception,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject(__('Backup database fallito'))
            ->greeting(__('Attenzione,'))
            ->line(__('Il backup automatico del database Servio non è riuscito.'))
            ->line(__('Driver: :driver', ['driver' => $this->backupLog->driver]))
            ->line(__('Errore: :error', ['error' => $this->exception->getMessage()]))
            ->line(__('Controlla i log applicativi e verifica che lo storage remoto sia raggiungibile.'))
            ->salutation(__('Il team Servio'));
    }
}
