# Data Inventory — BeachOrder

**Last updated:** 10 August 2026  
**GDPR Art. 30 — Record of processing activities**

This document mirrors the system configuration in `backend/config/privacy.php` and the admin API endpoint `GET /api/admin/privacy/inventory`.

## Personal data categories

| Category | Description | Legal basis | Retention | Tables |
|----------|-------------|-------------|-----------|--------|
| staff_accounts | Staff/admin user accounts | Contract | Until deletion | users |
| tenant_business | Business customer registration | Contract | Until tenant offboarding | tenants |
| customer_sessions | Anonymous QR ordering sessions | Legitimate interest | 365 days (anonymise) | orders, waiter_calls, loyalty_*, location_access_tokens |
| auth_sessions | Staff Sanctum tokens | Contract | 30 days after expiry | personal_access_tokens, sessions |
| audit_logs | Security audit (PII redacted) | Legitimate interest | 730 days | audit_logs |
| password_recovery | Password reset tokens | Contract | 24 hours | password_reset_tokens |

## Client-side storage (browser)

| Key | Purpose | Consent required |
|-----|---------|------------------|
| bo_session | Guest session UUID | Yes (banner) |
| bo_cart:* | Cart persistence | Yes |
| bo_token | Staff auth | No (contract) |
| bo_cookie_consent | Consent choice | No |

## Minimisation measures

- Guest ordering uses anonymous UUID — no account required
- Customer name is optional
- Audit logs redact passwords, mask emails, truncate IPs
- Scheduled purge job (`privacy:purge`) runs daily at 03:00

## API endpoints

| Endpoint | Access | Purpose |
|----------|--------|---------|
| GET /api/privacy/info | Public | Retention + inventory summary |
| GET /api/legal/{doc} | Public | Legal documents (markdown) |
| GET /api/me/export | Staff auth | Personal data export |
| DELETE /api/me/account | Staff auth | Account deletion |
| POST /api/t/{tenant}/privacy/erase-session | Public (tenant) | Guest session erasure |
| GET /api/admin/privacy/inventory | Admin | Full data audit |

## Related documents

- [Privacy Policy](legal/privacy.it.md)
- [DPA](legal/dpa.it.md)
- [Controller/Processor Roles](legal/data-processing-roles.it.md)
