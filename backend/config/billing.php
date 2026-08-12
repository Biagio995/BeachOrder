<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payment tolerance (grace period)
    |--------------------------------------------------------------------------
    |
    | Days after the first failed payment before the tenant is suspended.
    | Stripe continues automatic payment retries during this window.
    |
    */

    'grace_period_days' => (int) env('BILLING_GRACE_PERIOD_DAYS', 7),

    'annual_price_cents' => (int) env('SUBSCRIPTION_ANNUAL_PRICE_CENTS', 29900),

    'annual_vat_note' => env('SUBSCRIPTION_VAT_NOTE', '+ VAT'),

    /*
    |--------------------------------------------------------------------------
    | Stripe
    |--------------------------------------------------------------------------
    */

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'price_annual' => env('STRIPE_PRICE_ANNUAL_ID'),
    ],

];
