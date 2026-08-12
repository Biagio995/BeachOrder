<?php

namespace App\Notifications;

use App\Models\Subscription;
use App\Support\TenantMailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Subscription $subscription) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tenant = $this->subscription->tenant;
        $graceDays = (int) config('billing.grace_period_days', 7);
        $graceEnds = $this->subscription->grace_period_ends_at;

        $mail = (new MailMessage)
            ->subject(__('Pagamento abbonamento non riuscito'))
            ->greeting(__('Ciao :name,', ['name' => $notifiable->name]))
            ->line(__('Il pagamento dell\'abbonamento per :tenant non è andato a buon fine.', [
                'tenant' => $tenant->name,
            ]))
            ->line(__('Stripe tenterà nuovamente il pagamento automaticamente.'));

        if ($graceEnds !== null) {
            $mail->line(__('Hai tempo fino al :date per regolarizzare la situazione prima della sospensione del servizio.', [
                'date' => $graceEnds->timezone($tenant->timezone ?? config('app.timezone'))->format('d/m/Y H:i'),
            ]));
        } elseif ($graceDays > 0) {
            $mail->line(__('Hai :days giorni di tolleranza prima della sospensione del servizio.', ['days' => $graceDays]));
        }

        return TenantMailMessage::apply($mail, $tenant);
    }
}
