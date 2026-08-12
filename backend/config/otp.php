<?php

return [

    /*
    |--------------------------------------------------------------------------
    | One-time passcodes (OTP)
    |--------------------------------------------------------------------------
    |
    | Used for password reset, email verification (tenant registration), and
    | the `mail:test-otp` artisan command. Codes are stored hashed in cache.
    |
    */

    'length' => (int) env('OTP_LENGTH', 6),

    /** Seconds until a code expires. */
    'ttl' => (int) env('OTP_TTL', 15 * 60),

    /** Failed verify attempts before the code is invalidated. */
    'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),

    'purposes' => [
        'password_reset' => 'password_reset',
        'email_verification' => 'email_verification',
        'test' => 'test',
    ],

];
