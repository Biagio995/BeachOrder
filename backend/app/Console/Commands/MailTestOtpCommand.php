<?php

namespace App\Console\Commands;

use App\Services\OtpService;
use Illuminate\Console\Command;

class MailTestOtpCommand extends Command
{
    protected $signature = 'mail:test-otp
                            {email : Destinatario della mail di test}
                            {--purpose=test : password_reset|email_verification|test}
                            {--show-code : Stampa il codice OTP generato in console}';

    protected $description = 'Invia una email di test con un codice OTP (forgot password / verifica email / test)';

    public function handle(OtpService $otp): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $purpose = (string) $this->option('purpose');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("Email non valida: {$email}");

            return self::FAILURE;
        }

        $allowed = array_values(config('otp.purposes', []));
        if (! in_array($purpose, $allowed, true)) {
            $this->error('Purpose non valido. Usa: '.implode(', ', $allowed));

            return self::FAILURE;
        }

        $this->info("Invio OTP ({$purpose}) a {$email}…");
        $this->line('Mailer: '.(string) config('mail.default'));
        $this->line('From: '.(string) config('mail.from.address'));

        try {
            $code = $otp->sendTest($email, $purpose);
        } catch (\Throwable $e) {
            $this->error('Invio fallito: '.$e->getMessage());
            report($e);

            return self::FAILURE;
        }

        $this->info('Email OTP inviata.');
        if ($this->option('show-code')) {
            $this->warn("Codice (solo per debug locale): {$code}");
        } else {
            $this->comment('Suggerimento: aggiungi --show-code per vedere il codice in console (solo in locale).');
        }

        if (config('mail.default') === 'log') {
            $this->comment('MAIL_MAILER=log → apri storage/logs/laravel.log per vedere il contenuto della mail.');
        }

        return self::SUCCESS;
    }
}
