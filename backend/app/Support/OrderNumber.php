<?php

namespace App\Support;

use Illuminate\Support\Str;

class OrderNumber
{
    public const DEFAULT_PREFIX = 'ORD';

    /**
     * Build a new order number: <PREFIX>-<YYMMDD>-<RANDOM>.
     */
    public static function generate(): string
    {
        return self::prefix().'-'.now()->format('ymd').'-'.Str::upper(Str::random(5));
    }

    /**
     * Normalized prefix from config('orders.number_prefix').
     */
    public static function prefix(): string
    {
        $prefix = strtoupper(trim((string) config('orders.number_prefix', self::DEFAULT_PREFIX)));

        if ($prefix === '' || preg_match('/^[A-Z0-9]{1,6}$/', $prefix) !== 1) {
            return self::DEFAULT_PREFIX;
        }

        return $prefix;
    }
}
