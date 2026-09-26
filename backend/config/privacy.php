<?php

/**
 * GDPR / privacy configuration — retention periods and data inventory.
 *
 * Retention values are in days unless noted. null = keep until manual deletion.
 */
return [

    'retention' => [
        // Completed/cancelled orders — anonymize PII, keep aggregates
        'orders_days' => (int) env('PRIVACY_RETENTION_ORDERS_DAYS', 365),

        // Waiter calls after resolution
        'waiter_calls_days' => (int) env('PRIVACY_RETENTION_WAITER_CALLS_DAYS', 90),

        // Expired QR access tokens
        'access_tokens_days' => (int) env('PRIVACY_RETENTION_ACCESS_TOKENS_DAYS', 7),

        // Loyalty accounts with no activity
        'loyalty_inactive_days' => (int) env('PRIVACY_RETENTION_LOYALTY_DAYS', 730),

        // Operational audit logs (PII redacted on write)
        'audit_logs_days' => (int) env('PRIVACY_RETENTION_AUDIT_LOGS_DAYS', 730),

        // Sanctum tokens past expiry
        'expired_tokens_days' => (int) env('PRIVACY_RETENTION_EXPIRED_TOKENS_DAYS', 30),

        // Password reset tokens
        'password_reset_tokens_hours' => (int) env('PRIVACY_RETENTION_PASSWORD_RESET_HOURS', 24),

        // Laravel DB sessions
        'sessions_days' => (int) env('PRIVACY_RETENTION_SESSIONS_DAYS', 14),
    ],

    /**
     * Register of personal data categories collected by the system (GDPR Art. 30).
     *
     * @return list<array<string, mixed>>
     */
    'data_inventory' => [
        [
            'category' => 'staff_accounts',
            'description' => 'Staff and admin user accounts for beach bar operators.',
            'fields' => ['name', 'email', 'password (hashed)', 'role', 'email_verified_at'],
            'legal_basis' => 'contract',
            'recipients' => ['platform operator'],
            'retention_key' => null,
            'tables' => ['users'],
        ],
        [
            'category' => 'tenant_business',
            'description' => 'Business customer (tenant) registration and settings.',
            'fields' => ['company name', 'slug', 'timezone', 'fiscal/POS config references'],
            'legal_basis' => 'contract',
            'recipients' => ['platform operator'],
            'retention_key' => null,
            'tables' => ['tenants'],
        ],
        [
            'category' => 'customer_sessions',
            'description' => 'Anonymous end-customer session identifiers (QR ordering).',
            'fields' => ['customer_session (UUID)', 'optional customer_name', 'order notes'],
            'legal_basis' => 'legitimate_interest',
            'recipients' => ['tenant (beach bar)', 'platform operator (processor)'],
            'retention_key' => 'orders_days',
            'tables' => ['orders', 'waiter_calls', 'loyalty_accounts', 'location_access_tokens'],
        ],
        [
            'category' => 'auth_sessions',
            'description' => 'Staff authentication tokens and session metadata.',
            'fields' => ['token hash', 'last_used_at', 'expires_at', 'ip_address'],
            'legal_basis' => 'contract',
            'recipients' => ['platform operator'],
            'retention_key' => 'expired_tokens_days',
            'tables' => ['personal_access_tokens', 'sessions'],
        ],
        [
            'category' => 'audit_logs',
            'description' => 'Security and operational audit trail (PII redacted).',
            'fields' => ['action', 'user_id', 'ip_address (truncated)', 'change snapshots (redacted)'],
            'legal_basis' => 'legitimate_interest',
            'recipients' => ['platform operator', 'tenant admin (scoped)'],
            'retention_key' => 'audit_logs_days',
            'tables' => ['audit_logs'],
        ],
        [
            'category' => 'password_recovery',
            'description' => 'Temporary password reset tokens.',
            'fields' => ['email', 'token (hashed)'],
            'legal_basis' => 'contract',
            'recipients' => ['platform operator'],
            'retention_key' => 'password_reset_tokens_hours',
            'tables' => ['password_reset_tokens'],
        ],
    ],

    'controller' => [
        'name' => env('PRIVACY_CONTROLLER_NAME', 'Dalposto'),
        'email' => env('PRIVACY_CONTROLLER_EMAIL', 'privacy@dalposto.example'),
        'address' => env('PRIVACY_CONTROLLER_ADDRESS', ''),
    ],

    'processor' => [
        'name' => env('PRIVACY_PROCESSOR_NAME', 'Dalposto Platform'),
        'email' => env('PRIVACY_PROCESSOR_EMAIL', 'dpo@dalposto.example'),
    ],

    /** Fields redacted from audit log snapshots. */
    'redact_fields' => [
        'password',
        'remember_token',
        'token',
        'credentials_ref',
    ],

];
