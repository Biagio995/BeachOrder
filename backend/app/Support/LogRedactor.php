<?php

namespace App\Support;

class LogRedactor
{
    /** @var list<string> */
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'token',
        'access_token',
        'refresh_token',
        'api_key',
        'api_token',
        'secret',
        'authorization',
        'credit_card',
        'card_number',
        'cvv',
        'ssn',
    ];

    /** @var list<string> */
    private const PII_KEYS = [
        'email',
        'customer_name',
        'customer_email',
        'phone',
        'mobile',
        'address',
        'ip_address',
    ];

    /**
     * @param  mixed  $data
     * @return mixed
     */
    public static function redact(mixed $data): mixed
    {
        if (is_array($data)) {
            $redacted = [];
            foreach ($data as $key => $value) {
                $redacted[$key] = self::redactKey((string) $key, $value);
            }

            return $redacted;
        }

        if (is_string($data)) {
            return self::redactString($data);
        }

        return $data;
    }

    /**
     * @param  mixed  $value
     * @return mixed
     */
    private static function redactKey(string $key, mixed $value): mixed
    {
        $normalized = strtolower($key);

        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if (str_contains($normalized, $sensitive)) {
                return '[REDACTED]';
            }
        }

        foreach (self::PII_KEYS as $pii) {
            if (str_contains($normalized, $pii)) {
                return is_string($value) ? self::maskPii($value) : '[REDACTED]';
            }
        }

        return self::redact($value);
    }

    public static function maskPii(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            [$local, $domain] = explode('@', $value, 2);

            return substr($local, 0, 1).'***@'.$domain;
        }

        if (strlen($value) <= 4) {
            return '***';
        }

        return substr($value, 0, 2).'***'.substr($value, -1);
    }

    private static function redactString(string $value): string
    {
        if (preg_match('/Bearer\s+\S+/i', $value)) {
            return 'Bearer [REDACTED]';
        }

        return $value;
    }
}
