<?php

namespace App\Support;

/**
 * Central demo-mode safety checks.
 *
 * DEMO_MODE=true marks a public demo install (used for the video campaign).
 * In demo mode Stripe may only run with test keys, printers/POS stay off and
 * per-tenant card payments are forced onto sandbox gateways.
 */
class DemoMode
{
    public static function enabled(): bool
    {
        return (bool) config('demo.enabled', false);
    }

    public static function printersEnabled(): bool
    {
        return (bool) config('demo.printers_enabled', false);
    }

    public static function posEnabled(): bool
    {
        return (bool) config('demo.pos_enabled', false);
    }

    public static function stripeSecret(): string
    {
        return (string) config('billing.stripe.secret', '');
    }

    public static function stripeKey(): string
    {
        return (string) config('billing.stripe.key', '');
    }

    /**
     * True when no live Stripe key is configured. Empty keys are safe
     * (payments simply report "not configured").
     */
    public static function stripeKeysAreTestOnly(): bool
    {
        foreach ([self::stripeSecret(), self::stripeKey()] as $key) {
            if ($key === '') {
                continue;
            }

            $isTest = str_starts_with($key, 'sk_test_')
                || str_starts_with($key, 'pk_test_')
                || str_starts_with($key, 'rk_test_')
                || str_starts_with($key, 'whsec_');

            if (! $isTest) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string> Human-readable violations, empty when safe.
     */
    public static function violations(): array
    {
        if (! self::enabled()) {
            return [];
        }

        $violations = [];

        if (! self::stripeKeysAreTestOnly()) {
            $violations[] = 'Live Stripe keys detected with DEMO_MODE=true. '
                .'Use test keys (sk_test_/pk_test_) or leave Stripe empty.';
        }

        if (self::printersEnabled()) {
            $violations[] = 'DEMO_PRINTERS_ENABLED=true: the demo will attempt real printer connections.';
        }

        if (self::posEnabled()) {
            $violations[] = 'DEMO_POS_ENABLED=true: the demo will attempt real POS connections.';
        }

        return $violations;
    }

    /**
     * Resolve the demo password for a role. Dev default ('password') applies
     * to local environments only; elsewhere a missing variable yields a
     * random password (printed once to the log by the seeder) so that no
     * documented default credential ever works in production.
     */
    public static function password(string $envVar, ?string $fallbackName = null): string
    {
        $configured = (string) env($envVar, '');

        if ($configured !== '') {
            return $configured;
        }

        if (app()->isLocal()) {
            return 'password';
        }

        $generated = bin2hex(random_bytes(12));

        logger()->warning('demo.password_generated', [
            'env' => $envVar,
            'hint' => 'Set '.$envVar.' explicitly; a random password was generated for '.($fallbackName ?? $envVar).'.',
        ]);

        return $generated;
    }
}
