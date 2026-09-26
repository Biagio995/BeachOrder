<?php

namespace App\Notifications;

use App\Models\Tenant;
use App\Services\OtpService;
use App\Support\TenantMailMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class OtpCodeNotification extends Notification
{
    public function __construct(
        public string $code,
        public string $purpose,
        public ?Tenant $tenant = null,
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
        $minutes = app(OtpService::class)->ttlMinutes();
        $name = is_object($notifiable) && isset($notifiable->name)
            ? (string) $notifiable->name
            : '';

        $mail = (new MailMessage)
            ->subject($this->subject())
            ->greeting($name !== '' ? __('Ciao :name,', ['name' => $name]) : __('Ciao,'))
            ->line($this->intro())
            ->line(new HtmlString($this->codeBlock()))
            ->line(__('Il codice scade tra :minutes minuti.', ['minutes' => $minutes]))
            ->line(__('Se non hai richiesto tu questa operazione, puoi ignorare questa email.'));

        return TenantMailMessage::apply($mail, $this->tenant, platform: true);
    }

    private function subject(): string
    {
        return match ($this->purpose) {
            OtpService::PURPOSE_PASSWORD_RESET => __('Ordequi — Codice reset password'),
            OtpService::PURPOSE_EMAIL_VERIFICATION => __('Ordequi — Codice verifica email'),
            default => __('Ordequi — Codice OTP di test'),
        };
    }

    private function intro(): string
    {
        return match ($this->purpose) {
            OtpService::PURPOSE_PASSWORD_RESET => __('Usa questo codice per reimpostare la password del tuo account Ordequi:'),
            OtpService::PURPOSE_EMAIL_VERIFICATION => __('Usa questo codice per verificare l\'email e attivare il tuo account Ordequi:'),
            default => __('Questa è una email di test. Il codice OTP generato è:'),
        };
    }

    private function codeBlock(): string
    {
        $code = e($this->code);

        return <<<HTML
<div style="margin:24px 0;text-align:center">
  <div style="display:inline-block;padding:16px 28px;border-radius:12px;background:#0f766e;color:#ffffff;font-size:32px;letter-spacing:10px;font-weight:700;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace">
    {$code}
  </div>
</div>
HTML;
    }
}
