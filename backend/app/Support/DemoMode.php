<?php

namespace App\Support;

/**
 * Central demo-mode safety checks.
 *
 * DEMO_MODE=true marks a public demo install (used for the video campaign).
 * Online payments are off product-wide (orders are pay-at-location), and in
 * demo mode printers/POS stay off while per-tenant card payments, if ever
 * configured, are forced onto sandbox gateways.
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

    /**
     * @return list<string> Human-readable violations, empty when safe.
     */
    public static function violations(): array
    {
        if (! self::enabled()) {
            return [];
        }

        $violations = [];

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
