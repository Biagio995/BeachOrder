<?php

namespace App\Notifications;

use App\Models\Subscription;
use App\Support\TenantMailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionReactivatedNotification extends Notification implements ShouldQueue
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

        return TenantMailMessage::apply(
            (new MailMessage)
                ->subject(__('Abbonamento riattivato'))
                ->greeting(__('Ciao :name,', ['name' => $notifiable->name]))
                ->line(__('Il pagamento per :tenant è stato ricevuto con successo.', [
                    'tenant' => $tenant->name,
                ]))
                ->line(__('Il servizio è di nuovo attivo e il tuo team può accedere normalmente.')),
            $tenant
        );
    }
}
