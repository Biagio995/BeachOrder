<?php

return [
    'force_stub' => env('POS_FORCE_STUB', false),
    'max_retries' => (int) env('POS_SYNC_MAX_RETRIES', 5),
    'retry_base_seconds' => (int) env('POS_SYNC_RETRY_BASE_SECONDS', 30),
];
