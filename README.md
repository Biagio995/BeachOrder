# PresaOrdini

A full-stack, multi-tenant SaaS platform for **digital restaurant ordering**. Customers scan a QR code at their table, browse an interactive menu, place orders and pay online — while kitchen staff and waiters manage everything in real-time from a dedicated dashboard.

---

## Table of Contents

- [Overview](#overview)
- [Key Features](#key-features)
- [Tech Stack](#tech-stack)
- [Architecture](#architecture)
- [Getting Started](#getting-started)
  - [Prerequisites](#prerequisites)
  - [Backend Setup](#backend-setup)
  - [Frontend Setup](#frontend-setup)
- [Project Structure](#project-structure)
- [User Roles](#user-roles)
- [Integrations](#integrations)
- [Internationalization](#internationalization)
- [Testing](#testing)
- [License](#license)

---

## Overview

PresaOrdini ("Order Taking" in Italian) is a white-label hospitality platform designed to modernize the order flow in restaurants, bars, and similar venues.

Each business onboards as an isolated **tenant** with its own menu, branding, locations, users, and subscription. Tenants can also connect to external POS systems, configure kitchen printers, and track sales analytics — all from a single back-office.

---

## Key Features

| Area | Capabilities |
|---|---|
| **Customer flow** | QR-code table access · Digital menu with categories, variants and add-ons · Cart · Online payment (Stripe) · Real-time order status · Waiter call button |
| **Kitchen & staff** | Live kitchen board · Waiter-call board · Real-time updates via WebSocket |
| **Admin back-office** | Menu management (categories, products, variants, add-ons, tags) · Location management · User & role management · Branding & appearance · Sales and product analytics · Subscription billing |
| **Printing** | ESC/POS over TCP · PrintNode · Local bridge driver · Per-station ticket routing |
| **POS integration** | SoftOne · Epsilon Pylon · Custom HTTP adapter · Mapping & sync log |
| **Multi-tenancy** | Row-level tenant isolation · Platform-level super-admin overview |
| **Payments & billing** | Stripe for per-order payments · Stripe subscription plans with automatic suspension on non-payment |
| **Auth** | OTP email code (passwordless) + Sanctum API tokens · Role/permission middleware |
| **GDPR** | Data retention policies · Data-subject export/deletion · Cookie consent |
| **Monitoring** | Health checks · Request metrics · Failed-job alerts · Backup monitoring |

---

## Tech Stack

### Backend — `backend/`

- **PHP 8.2+** with **Laravel 12**
- **Laravel Sanctum** — API authentication
- **Laravel Reverb** — WebSocket server for real-time events
- **Stripe PHP SDK** — payments & subscriptions
- **PHPUnit** — feature and unit tests

### Frontend — `frontend/`

- **Vue 3** + **TypeScript**
- **Vuetify 3** — Material Design component library
- **Pinia** — state management
- **Vue Router 4**
- **vue-i18n 9** — internationalization
- **Laravel Echo + Pusher-js** — real-time WebSocket client
- **Stripe.js** — client-side payment handling
- **Vite 8** — build tool

---

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                        Browser / PWA                        │
│                                                             │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────────┐  │
│  │   Customer   │  │    Staff /   │  │      Admin       │  │
│  │   (QR menu,  │  │   Kitchen    │  │   (back-office)  │  │
│  │  cart, pay)  │  │   (live view)│  │                  │  │
│  └──────┬───────┘  └──────┬───────┘  └────────┬─────────┘  │
│         │    Vue 3 SPA (Vuetify + Pinia)       │            │
└─────────┼──────────────────────────────────────┼────────────┘
          │ REST API (Sanctum)                   │ WebSocket (Reverb)
┌─────────▼──────────────────────────────────────▼────────────┐
│                    Laravel 12 Backend                        │
│                                                             │
│  Controllers · Services · Jobs · Events · Notifications     │
│  Multi-tenant scope · Role & permission middleware          │
│                                                             │
│  ┌──────────┐  ┌─────────┐  ┌──────────┐  ┌────────────┐   │
│  │  Stripe  │  │   POS   │  │ Printers │  │   Mail /   │   │
│  │ Payments │  │ Adapters│  │ (ESC/POS)│  │ Monitoring │   │
│  └──────────┘  └─────────┘  └──────────┘  └────────────┘   │
│                                                             │
│                        Database                             │
└─────────────────────────────────────────────────────────────┘
```

---

## Getting Started

### Prerequisites

- PHP 8.2+ and Composer
- Node.js 20+ and npm
- A database supported by Laravel (MySQL, PostgreSQL, SQLite for local dev)
- A [Stripe](https://stripe.com) account (for payments)
- (Optional) A [PrintNode](https://www.printnode.com) account for cloud printing

### Backend Setup

```bash
cd backend

# Install PHP dependencies
composer install

# Copy and configure environment
cp .env.example .env

# Generate application key
php artisan key:generate

# Run migrations (and optional seeders)
php artisan migrate --seed

# Start the development server
php artisan serve

# Start the WebSocket server (in a separate terminal)
php artisan reverb:start

# Start the queue worker (in a separate terminal)
php artisan queue:work
```

Key `.env` values to configure:

| Variable | Description |
|---|---|
| `DB_*` | Database connection |
| `STRIPE_KEY` / `STRIPE_SECRET` / `STRIPE_WEBHOOK_SECRET` | Stripe credentials |
| `MAIL_*` | Mail driver for OTP codes and notifications |
| `REVERB_*` | WebSocket server settings |
| `PRINTNODE_API_KEY` | PrintNode integration (optional) |

### Frontend Setup

```bash
cd frontend

# Install dependencies
npm install

# Copy and configure environment
cp .env.example .env

# Start the development server
npm run dev

# Build for production
npm run build
```

Key `.env` values to configure:

| Variable | Description |
|---|---|
| `VITE_API_BASE_URL` | Backend API URL |
| `VITE_REVERB_*` | WebSocket connection settings |
| `VITE_STRIPE_KEY` | Stripe publishable key |

---

## Project Structure

```
PresaOrdini/
├── backend/          # Laravel 12 REST API
│   ├── app/
│   │   ├── Console/      # Artisan commands (backup, monitoring, data retention)
│   │   ├── Contracts/    # POS adapter interfaces
│   │   ├── Events/       # Real-time events (orders, waiter calls)
│   │   ├── Http/         # Controllers, middleware
│   │   ├── Jobs/         # Queue jobs (POS sync, subscription suspension)
│   │   ├── Models/       # Eloquent models (multi-tenant scoped)
│   │   ├── Notifications/ # Email notifications
│   │   └── Services/     # Business logic (payments, printing, POS, loyalty, …)
│   ├── database/
│   │   └── migrations/   # Full schema history
│   ├── routes/
│   │   └── api.php       # All API endpoints
│   └── tests/            # Feature & unit tests
│
└── frontend/         # Vue 3 SPA
    └── src/
        ├── api/          # Axios API client
        ├── components/   # Reusable UI components (admin / customer / staff / shared)
        ├── composables/  # Vue composables
        ├── locales/      # i18n translation files
        ├── router/       # Vue Router configuration
        ├── stores/       # Pinia stores
        └── views/        # Page-level views (admin / customer / staff / legal)
```

---

## User Roles

| Role | Description |
|---|---|
| **Customer** | Accesses the platform via a QR-code link. No login required. Can browse the menu, place orders, pay, track order status, and call a waiter. |
| **Staff / Kitchen** | Logs in to view and manage live orders on the kitchen board or waiter-call board. |
| **Tenant Admin** | Full back-office access: manages menu, locations, users, analytics, integrations, branding, and billing. |
| **Platform Super-Admin** | Cross-tenant oversight: can view all tenants, manage subscriptions, and access platform-level analytics. |

---

## Integrations

### POS Systems

PresaOrdini forwards orders to external point-of-sale systems via a pluggable adapter pattern:

- **SoftOne** — REST adapter (`SoftOneAdapter`)
- **Epsilon Pylon** — REST adapter (`EpsilonPylonAdapter`)
- **Custom** — generic configurable HTTP adapter (`CustomPosAdapter`)

### Printing

Ticket printing is handled by a driver-based service:

- **ESC/POS over TCP** — direct printer socket connection
- **PrintNode** — cloud print service
- **Local Bridge** — custom local agent bridge

### Payments

- **Stripe** — customer-facing order payments (Payment Intents) and SaaS subscription billing (Stripe Billing with webhook-driven lifecycle management)

---

## Internationalization

The frontend supports four languages out of the box:

| Code | Language |
|---|---|
| `it` | Italian |
| `en` | English |
| `el` | Greek |
| `de` | German |

Translation files live in `frontend/src/locales/`. Product names in the backend support bilingual entries managed via `AutoTranslator` and the `HasTranslatedName` concern.

---

## Testing

The backend includes a comprehensive test suite covering all major features:

```bash
cd backend
php artisan test
```

Test coverage includes: authentication & security, order flow, online payments, kitchen printing, POS integration, subscription billing, tenant isolation, role permissions, analytics, GDPR/privacy, monitoring, and backup/restore.

---

## License

This project is proprietary software. All rights reserved.
