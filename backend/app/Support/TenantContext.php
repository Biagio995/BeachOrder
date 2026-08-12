<?php

namespace App\Support;

use App\Models\Tenant;

class TenantContext
{
    protected static ?Tenant $tenant = null;

    public static function set(?Tenant $tenant): void
    {
        static::$tenant = $tenant;
    }

    public static function get(): ?Tenant
    {
        return static::$tenant;
    }

    public static function id(): ?int
    {
        return static::$tenant?->id;
    }

    public static function check(): bool
    {
        return static::$tenant !== null;
    }

    public static function require(): Tenant
    {
        $tenant = static::$tenant;

        if (! $tenant) {
            abort(400, 'Tenant context required');
        }

        return $tenant;
    }

    public static function clear(): void
    {
        static::$tenant = null;
    }
}
