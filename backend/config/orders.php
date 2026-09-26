<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Order number prefix
    |--------------------------------------------------------------------------
    |
    | Prefixed onto new order numbers as <PREFIX>-<YYMMDD>-<RANDOM>.
    | Normalized at generation time: trim, uppercase; empty or values that
    | do not match ^[A-Z0-9]{1,6}$ fall back to ORD.
    | Demo environments typically use DP.
    |
    */

    'number_prefix' => env('ORDER_NUMBER_PREFIX', 'ORD'),

];
