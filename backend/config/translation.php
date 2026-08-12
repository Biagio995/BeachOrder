<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Automatic content translation
    |--------------------------------------------------------------------------
    |
    | Catalog text (product/category/tag names & descriptions) is stored only in
    | primary locales (admin input: Greek + English). Every other locale is
    | machine-translated at request time via the driver below and cached —
    | secondary keys already present in the JSON map are ignored.
    |
    | Drivers:
    | - mymemory  Free HTTP API (default, no key). Good for demos / low volume.
    | - null      Disabled — fall back to the best primary source text.
    |
    */

    'enabled' => (bool) env('TRANSLATION_AUTO', true),

    'driver' => env('TRANSLATION_DRIVER', 'mymemory'),

    'supported_locales' => ['it', 'en', 'el', 'de'],

    /** Locales the admin maintains manually (stored in the JSON name map). */
    'primary_locales' => ['el', 'en'],

    /** Preferred source order when picking text to translate from. */
    'source_priority' => ['en', 'el', 'it', 'de'],

    'mymemory' => [
        'endpoint' => env('TRANSLATION_MYMEMORY_URL', 'https://api.mymemory.translated.net/get'),
        'email' => env('TRANSLATION_MYMEMORY_EMAIL'), // optional, raises free quota
        'timeout' => 8,
    ],

    /** Cache successful API translations (seconds). */
    'cache_ttl' => (int) env('TRANSLATION_CACHE_TTL', 60 * 60 * 24 * 30),

];
