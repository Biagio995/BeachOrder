<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(function () {
            $rule = Password::min(8);

            return $this->app->isProduction()
                ? $rule->mixedCase()->numbers()->uncompromised()
                : $rule;
        });

        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            $base = rtrim((string) config('app.frontend_url'), '/');

            return $base.'/reset-password?'.http_build_query([
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);
        });

        VerifyEmail::createUrlUsing(function (object $notifiable) {
            $id = $notifiable->getKey();
            $hash = sha1($notifiable->getEmailForVerification());
            $expires = Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60));

            $signed = URL::temporarySignedRoute(
                'verification.verify',
                $expires,
                ['id' => $id, 'hash' => $hash]
            );

            parse_str(parse_url($signed, PHP_URL_QUERY) ?: '', $query);
            $base = rtrim((string) config('app.frontend_url'), '/');

            return $base.'/verify-email?'.http_build_query([
                'id' => $id,
                'hash' => $hash,
                'expires' => $query['expires'] ?? null,
                'signature' => $query['signature'] ?? null,
            ]);
        });
    }
}
