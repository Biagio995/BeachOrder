<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Health checks
    |--------------------------------------------------------------------------
    */

    'frontend_url' => env('FRONTEND_URL'),

    'frontend_check_enabled' => env('MONITORING_FRONTEND_CHECK', true),

    'frontend_timeout_seconds' => (int) env('MONITORING_FRONTEND_TIMEOUT', 3),

    /*
    |--------------------------------------------------------------------------
    | Queue worker liveness (updated by queue:work WorkerStarting / Looping)
    |--------------------------------------------------------------------------
    */

    'queue_heartbeat_key' => 'monitoring:queue:heartbeat',

    'queue_heartbeat_max_age_seconds' => (int) env('MONITORING_QUEUE_HEARTBEAT_MAX_AGE', 120),

    'failed_jobs_alert_threshold' => (int) env('MONITORING_FAILED_JOBS_THRESHOLD', 5),

    /*
    |--------------------------------------------------------------------------
    | Payment webhooks
    |--------------------------------------------------------------------------
    */

    'webhook_last_success_key' => 'monitoring:webhooks:last_success',

    'webhook_last_failure_key' => 'monitoring:webhooks:last_failure',

    'webhook_stale_after_seconds' => (int) env('MONITORING_WEBHOOK_STALE_AFTER', 86400),

    /*
    |--------------------------------------------------------------------------
    | Database backups
    |--------------------------------------------------------------------------
    */

    'backup_stale_after_seconds' => (int) env('MONITORING_BACKUP_STALE_AFTER', 90000),

    /*
    |--------------------------------------------------------------------------
    | Request metrics (error rate & response time)
    |--------------------------------------------------------------------------
    */

    'metrics_key' => 'monitoring:metrics:requests',

    'metrics_window_seconds' => (int) env('MONITORING_METRICS_WINDOW', 300),

    'metrics_max_samples' => (int) env('MONITORING_METRICS_MAX_SAMPLES', 500),

    'error_rate_alert_threshold' => (float) env('MONITORING_ERROR_RATE_THRESHOLD', 0.05),

    'response_time_alert_ms' => (int) env('MONITORING_RESPONSE_TIME_ALERT_MS', 3000),

    /*
    |--------------------------------------------------------------------------
    | Critical error alerts (Slack webhook)
    |--------------------------------------------------------------------------
    */

    'alert_slack_webhook' => env('MONITORING_ALERT_SLACK_WEBHOOK'),

    'alert_cooldown_seconds' => (int) env('MONITORING_ALERT_COOLDOWN', 300),

];
