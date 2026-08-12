<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Automatic backups
    |--------------------------------------------------------------------------
    */

    'enabled' => env('BACKUP_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Schedule frequency: hourly, daily, weekly
    |--------------------------------------------------------------------------
    */

    'frequency' => env('BACKUP_FREQUENCY', 'daily'),

    /*
    |--------------------------------------------------------------------------
    | Retention (days) — backups older than this are deleted locally and remotely
    |--------------------------------------------------------------------------
    */

    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Local staging directory (temporary; remote copy is the durable store)
    |--------------------------------------------------------------------------
    */

    'local_disk' => env('BACKUP_LOCAL_DISK', 'backups'),

    /*
    |--------------------------------------------------------------------------
    | Remote disk for off-server storage (s3 / MinIO in production)
    |--------------------------------------------------------------------------
    */

    'remote_disk' => env('BACKUP_REMOTE_DISK', 's3'),

    'remote_path_prefix' => env('BACKUP_REMOTE_PATH', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Encrypt backup files before upload (AES-256 via Laravel Crypt / APP_KEY)
    |--------------------------------------------------------------------------
    */

    'encrypt' => env('BACKUP_ENCRYPT', true),

    /*
    |--------------------------------------------------------------------------
    | Alerting on backup failure
    |--------------------------------------------------------------------------
    */

    'alert_email' => env('BACKUP_ALERT_EMAIL'),

    'alert_slack_webhook' => env('BACKUP_ALERT_SLACK_WEBHOOK'),

];
