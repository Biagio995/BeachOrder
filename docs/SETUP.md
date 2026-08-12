# Setup operativo

## Requisiti

- PHP 8.2+
- Composer
- Node 20+
- Docker Desktop (opzionale: PostgreSQL, Redis, MinIO)

## Prima installazione

```bash
# Infra opzionale
docker compose up -d

# Backend
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve

# Realtime (altro terminale)
php artisan reverb:start

# Frontend
cd ../frontend
npm install
npm run dev
```

## DevTunnel (accesso remoto)

Se condividi l’app via **DevTunnel** (es. `https://xxxx-5173.euw.devtunnels.ms`), **non usare** `npm run dev`.

Vite in dev mode carica centinaia di moduli JS separati: il tunnel va in timeout e dopo il login compare una **pagina bianca**.

```bash
cd frontend
npm run dev:tunnel
```

Questo comando fa build + `vite preview` sulla porta 5173 (poche richieste HTTP, adatto al tunnel).
Il backend deve restare in esecuzione su `8000` — le chiamate `/api` passano dal proxy di Vite preview.

In VS Code/Cursor: **Terminal → Run Task → Frontend: DevTunnel (5173)**.

## Flusso demo

1. Cliente: apri `http://localhost:5173/q/umbrella12`
2. Aggiungi prodotti → carrello → invia ordine
3. Bar: login `bar@beachorder.test` / `password` → `/kitchen`
4. Cameriere: login `waiter@beachorder.test` → `/waiter`
5. Admin: `admin@beachorder.test` → `/admin` (prezzi, QR, utenti)

## Sicurezza MVP

- Rate limiting su login, ordini, waiter-call
- Sanctum token per staff
- Middleware `role:` per RBAC
- Audit log su CRUD e cambi stato ordine
