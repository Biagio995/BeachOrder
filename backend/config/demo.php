<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Demo mode (public demo / video campaign)
    |--------------------------------------------------------------------------
    |
    | When DEMO_MODE=true the app runs a safe public demo:
    | - Online payments stay off product-wide: customers order and pay at the
    |   venue (pay_at_location). No payment keys are needed for the demo.
    | - Kitchen printers and POS integrations are disabled unless explicitly
    |   re-enabled via DEMO_PRINTERS_ENABLED / DEMO_POS_ENABLED (default off,
    |   so the app never attempts a TCP/printer/POS connection).
    | - Per-tenant card payments, if ever configured, are forced to sandbox
    |   gateways (see NexiXPayService).
    |
    */

    'enabled' => filter_var(env('DEMO_MODE', false), FILTER_VALIDATE_BOOLEAN),

    // Seed the demo venue on container boot (entrypoint runs DemoSeeder).
    'seed' => filter_var(env('DEMO_SEED', false), FILTER_VALIDATE_BOOLEAN),

    // Slug of the single demo tenant created by DemoSeeder.
    'tenant_slug' => env('DEMO_TENANT_SLUG', 'lido-azzurra'),

    /*
    | Public base URL printed inside QR codes. Dedicated DEMO_PUBLIC_URL wins,
    | then the generic PUBLIC_URL / FRONTEND_URL, then APP_URL.
    */
    'public_url' => env(
        'DEMO_PUBLIC_URL',
        env('PUBLIC_URL', env('FRONTEND_URL', env('APP_URL', 'http://localhost')))
    ),

    // Where `demo:export-qr` writes PNG files.
    'qr_output_path' => env('DEMO_QR_OUTPUT', storage_path('app/demo-qr')),

    'qr_size' => (int) env('DEMO_QR_SIZE', 640),
    'qr_margin' => (int) env('DEMO_QR_MARGIN', 16),

    // Demo staff credentials. Passwords come from env; the 'password'
    // fallback applies to local development only, never production.
    'admin_email' => env('DEMO_ADMIN_EMAIL', 'admin@lido-azzurra.demo'),
    'staff_email' => env('DEMO_STAFF_EMAIL', 'cucina@lido-azzurra.demo'),
    'waiter_email' => env('DEMO_WAITER_EMAIL', 'sala@lido-azzurra.demo'),

    // Hardware integrations stay off in demo unless explicitly enabled.
    'printers_enabled' => filter_var(env('DEMO_PRINTERS_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'pos_enabled' => filter_var(env('DEMO_POS_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

];
