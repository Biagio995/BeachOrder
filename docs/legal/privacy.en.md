# Privacy Policy

**Last updated:** 10 August 2026  
**Data controller:** Servio (privacy@servio.example)

## 1. Who we are

Servio is a SaaS platform enabling restaurants, bars and pizzerias to manage QR-code orders. For guest ordering data, the **data controller** is the individual venue (tenant); Servio acts as **data processor** on behalf of the business customer.

## 2. Data we collect

### Staff users (venue operators)
- Name, email address, password (encrypted)
- Role and tenant affiliation
- Terms acceptance timestamp

### Guests (end customers via QR)
- Anonymous session identifier (UUID) — no registration required
- Optional name and order notes (only if voluntarily provided)
- Language preference (localStorage)

### Technical data
- IP address (truncated in audit logs)
- Staff authentication tokens (Sanctum)
- Operational logs (security actions, with masked PII)

**We do not collect** unnecessary data: no advertising tracking, no behavioural profiling.

## 3. Purposes and legal basis

| Purpose | Legal basis |
|---------|-------------|
| SaaS service delivery | Contract performance (Art. 6(1)(b) GDPR) |
| QR order management | Legitimate interest of controller (venue) / contract |
| Security and audit | Legitimate interest (Art. 6(1)(f) GDPR) |
| Legal compliance | Legal obligation (Art. 6(1)(c) GDPR) |

## 4. Data retention

| Category | Period |
|----------|--------|
| Completed orders | 365 days (then anonymised) |
| Waiter calls | 90 days |
| Expired QR access tokens | 7 days |
| Audit logs | 730 days |
| Staff accounts | Until account deletion |

Periods are configurable via environment variables (`PRIVACY_RETENTION_*`).

## 5. Your rights

Under the GDPR (Art. 15–22) you have the right to:

- **Access** and **portability** of your data (export from staff area)
- **Rectification** of inaccurate data
- **Erasure** ("right to be forgotten") — available for staff accounts and guest sessions
- **Restriction** and **objection** to processing
- **Complaint** to your supervisory authority

To exercise your rights: privacy@servio.example

## 6. Cookies and local storage

Servio **does not use tracking cookies**. It uses localStorage/sessionStorage for essential functionality (order session, cart, language, consent). See our [Cookie Policy](/cookies).

## 7. Sub-processors

Infrastructure may include hosting, database and email providers. An updated list is available on request and in the DPA for business customers.

## 8. Transfers outside the EU

Data is processed preferably in the EU/EEA. Any transfers use adequate safeguards (SCCs, adequacy decisions).

## 9. Changes

Updates will be published on this page with a revision date.
