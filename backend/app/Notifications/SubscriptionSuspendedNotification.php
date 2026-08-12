<?php

namespace App\Notifications;

use App\Models\Subscription;
use App\Support\TenantMailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionSuspendedNotification extends Notification implements ShouldQueue
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
                ->subject(__('Servizio sospeso per mancato pagamento'))
                ->greeting(__('Ciao :name,', ['name' => $notifiable->name]))
                ->line(__('L\'abbonamento di :tenant è stato sospeso perché il pagamento non è stato regolarizzato entro il periodo di tolleranza.', [
                    'tenant' => $tenant->name,
                ]))
                ->line(__('I tuoi dati (menu, ordini, configurazioni) sono conservati e non verranno cancellati.'))
                ->line(__('Completa il pagamento per riattivare automaticamente il servizio.')),
            $tenant
        );
    }
}
