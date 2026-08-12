<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\OtpCodeNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class OtpService
{
    public const PURPOSE_PASSWORD_RESET = 'password_reset';

    public const PURPOSE_EMAIL_VERIFICATION = 'email_verification';

    public const PURPOSE_TEST = 'test';

    /**
     * Generate an OTP, store it hashed, and email it to the user.
     */
    public function send(User $user, string $purpose): string
    {
        $code = $this->generateAndStore($purpose, $user->email);

        $user->notify(new OtpCodeNotification(
            code: $code,
            purpose: $purpose,
            tenant: $user->tenant,
        ));

        return $code;
    }

    /**
     * Send a test OTP to an arbitrary address (no User required).
     */
    public function sendTest(string $email, string $purpose = self::PURPOSE_TEST): string
    {
        $purpose = $this->normalizePurpose($purpose);
        $code = $this->generateAndStore($purpose, $email);

        Notification::route('mail', $email)
            ->notify(new OtpCodeNotification(
                code: $code,
                purpose: $purpose,
                tenant: null,
            ));

        return $code;
    }

    /**
     * Verify and consume a valid OTP. Throws ValidationException on failure.
     */
    public function verifyOrFail(string $purpose, string $email, string $code): void
    {
        if (! $this->verify($purpose, $email, $code)) {
            throw ValidationException::withMessages([
                'code' => [__('Codice non valido o scaduto.')],
            ]);
        }
    }

    /**
     * Verify and consume a valid OTP. Returns false on failure.
     */
    public function verify(string $purpose, string $email, string $code): bool
    {
        $purpose = $this->normalizePurpose($purpose);
        $email = $this->normalizeEmail($email);
        $code = preg_replace('/\s+/', '', trim($code)) ?? '';
        $key = $this->cacheKey($purpose, $email);
        $payload = Cache::get($key);

        if (! is_array($payload) || empty($payload['hash'])) {
            return false;
        }

        $attempts = (int) ($payload['attempts'] ?? 0);
        $maxAttempts = (int) config('otp.max_attempts', 5);

        if ($attempts >= $maxAttempts) {
            Cache::forget($key);

            return false;
        }

        if (! hash_equals((string) $payload['hash'], hash('sha256', $code))) {
            $payload['attempts'] = $attempts + 1;
            if ($payload['attempts'] >= $maxAttempts) {
                Cache::forget($key);
            } else {
                Cache::put($key, $payload, $this->remainingTtl($payload));
            }

            return false;
        }

        Cache::forget($key);

        return true;
    }

    public function generateAndStore(string $purpose, string $email): string
    {
        $purpose = $this->normalizePurpose($purpose);
        $email = $this->normalizeEmail($email);
        $length = max(4, min(8, (int) config('otp.length', 6)));
        $code = str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
        $ttl = max(60, (int) config('otp.ttl', 900));

        Cache::put($this->cacheKey($purpose, $email), [
            'hash' => hash('sha256', $code),
            'attempts' => 0,
            'expires_at' => now()->addSeconds($ttl)->getTimestamp(),
        ], $ttl);

        return $code;
    }

    public function ttlMinutes(): int
    {
        return max(1, (int) ceil(((int) config('otp.ttl', 900)) / 60));
    }

    private function cacheKey(string $purpose, string $email): string
    {
        return 'bo_otp:'.$purpose.':'.hash('sha256', $email);
    }

    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private function normalizePurpose(string $purpose): string
    {
        $allowed = array_values(config('otp.purposes', [
            self::PURPOSE_PASSWORD_RESET,
            self::PURPOSE_EMAIL_VERIFICATION,
            self::PURPOSE_TEST,
        ]));

        if (! in_array($purpose, $allowed, true)) {
            return self::PURPOSE_TEST;
        }

        return $purpose;
    }

    /**
     * @param  array{expires_at?: int}  $payload
     */
    private function remainingTtl(array $payload): int
    {
        $expiresAt = (int) ($payload['expires_at'] ?? 0);
        $remaining = $expiresAt - time();

        return max(1, $remaining > 0 ? $remaining : (int) config('otp.ttl', 900));
    }
}
