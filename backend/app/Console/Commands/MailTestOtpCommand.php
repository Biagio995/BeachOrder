<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\OtpService;
use Illuminate\Console\Command;

class MailTestOtpCommand extends Command
{
    protected $signature = 'mail:test-otp
                            {email : Destinatario della mail di test}
                            {--purpose= : password_reset|email_verification|test (default: entrambe)}
                            {--all : Invia recupero password e verifica nuovo tenant}
                            {--tenant= : Slug tenant per branding (es. azure-beach)}
                            {--show-code : Stampa il codice OTP generato in console}';

    protected $description = 'Invia email di test: recupero password e/o verifica email nuovo tenant';

    public function handle(OtpService $otp): int
    {
        $email = strtolower(trim((string) $this->argument('email')));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("Email non valida: {$email}");

            return self::FAILURE;
        }

        $tenant = $this->resolveTenant();
        if ($tenant === false) {
            return self::FAILURE;
        }

        $purposes = $this->purposes();
        if ($purposes === []) {
            return self::FAILURE;
        }

        $this->line('Mailer: '.(string) config('mail.default'));
        $this->line('SMTP: '.(string) config('mail.mailers.smtp.host').':'.(string) config('mail.mailers.smtp.port'));
        $this->line('From: '.(string) config('mail.from.address'));
        if ($tenant) {
            $this->line("Tenant: {$tenant->name} ({$tenant->slug})");
        }

        foreach ($purposes as $purpose) {
            $this->info("Invio OTP ({$purpose}) a {$email}…");

            try {
                $code = $otp->sendTest($email, $purpose, $tenant);
            } catch (\Throwable $e) {
                $this->error('Invio fallito: '.$e->getMessage());
                $this->hintInbox();
                report($e);

                return self::FAILURE;
            }

            $this->info("Email {$purpose} inviata.");
            if ($this->option('show-code')) {
                $this->warn("Codice (solo per debug locale): {$code}");
            }
        }

        if (! $this->option('show-code')) {
            $this->comment('Suggerimento: aggiungi --show-code per vedere i codici in console (solo in locale).');
        }

        $this->hintInbox();

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function purposes(): array
    {
        if ($this->option('all')) {
            return [
                OtpService::PURPOSE_PASSWORD_RESET,
                OtpService::PURPOSE_EMAIL_VERIFICATION,
            ];
        }

        $purpose = trim((string) $this->option('purpose'));
        if ($purpose === '') {
            return [
                OtpService::PURPOSE_PASSWORD_RESET,
                OtpService::PURPOSE_EMAIL_VERIFICATION,
            ];
        }

        $allowed = array_values(config('otp.purposes', []));
        if (! in_array($purpose, $allowed, true)) {
            $this->error('Purpose non valido. Usa: '.implode(', ', $allowed).' oppure --all');

            return [];
        }

        return [$purpose];
    }

    private function resolveTenant(): Tenant|false|null
    {
        $slug = trim((string) $this->option('tenant'));
        if ($slug === '') {
            return null;
        }

        $tenant = Tenant::query()->where('slug', $slug)->first();
        if (! $tenant) {
            $this->error("Tenant non trovato: {$slug}");

            return false;
        }

        return $tenant;
    }

    private function hintInbox(): void
    {
        $mailer = (string) config('mail.default');
        $host = (string) config('mail.mailers.smtp.host');
        $port = (int) config('mail.mailers.smtp.port');

        if ($mailer === 'log') {
            $this->comment('MAIL_MAILER=log → apri storage/logs/laravel.log per vedere il contenuto della mail.');

            return;
        }

        if ($mailer === 'smtp' && in_array($host, ['127.0.0.1', 'localhost'], true) && $port === 1025) {
            $this->comment('Inbox di test: http://127.0.0.1:8025');
            $this->comment('Se è vuota: docker compose up -d mailpit  oppure  bash scripts/start-mail-test.sh');
        }
    }
}
