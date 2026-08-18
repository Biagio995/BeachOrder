# Setup operativo

## Requisiti

- PHP 8.2+
- Composer
- Node 20+
- Docker Desktop (opzionale: PostgreSQL, Redis, MinIO, Mailpit)

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

## Mail di test (recupero password e nuovo tenant)

In locale le email (OTP reset password e verifica email alla registrazione tenant) vanno in **Mailpit**.

```bash
# dalla root del repo
docker compose up -d mailpit
# oppure, se Docker non è avviato:
bash scripts/start-mail-test.sh
```

Inbox: [http://127.0.0.1:8025](http://127.0.0.1:8025)

Nel `backend/.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_FROM_ADDRESS="hello@servio.test"
```

Invio di prova (entrambe le mail):

```bash
cd backend
php artisan mail:test-otp tuonome@example.com --tenant=azure-beach --show-code
```

- `--purpose=password_reset` solo recupero password
- `--purpose=email_verification` solo verifica email nuovo tenant
- `--tenant=azure-beach` applica il branding del locale demo (Pizzeria Bella)

Dall’app: **Accesso staff → Password dimenticata**, oppure **Registra il tuo locale**. Le mail compaiono in Mailpit, non nella casella reale.

Senza Docker: `MAIL_MAILER=log` e leggi `backend/storage/logs/laravel.log`.

## Flusso demo

1. Cliente: apri `http://localhost:5173/t/azure-beach/q/table3`
2. Aggiungi prodotti → carrello → invia ordine
3. Bar: login `bar@servio.test` / `password` → `/kitchen`
4. Cameriere: login `waiter@servio.test` → `/waiter`
5. Admin: `admin@servio.test` → `/admin` (prezzi, QR, utenti)

## Sicurezza MVP

- Rate limiting su login, ordini, waiter-call
- Sanctum token per staff
- Middleware `role:` per RBAC
- Audit log su CRUD e cambi stato ordine
