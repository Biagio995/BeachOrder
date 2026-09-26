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

Per il servizio demo impostare `ORDER_NUMBER_PREFIX=DP` in `backend/.env` (ordine es. `DP-260926-ABCDE`).

1. Cliente: apri `http://localhost:5173/t/azure-beach/q/table3`
2. Aggiungi prodotti → carrello → invia ordine
3. Bar: login `bar@servio.test` / `password` → `/kitchen`
4. Cameriere: login `waiter@servio.test` → `/waiter`
5. Admin: `admin@servio.test` → `/admin` (prezzi, QR, utenti)

## Deploy produzione (Dockerfile)

Il repo include un’immagine all-in-one (frontend Vue + API Laravel + queue + Reverb + scheduler) dietro nginx sulla porta **80**.

### File rilevanti

| File | Ruolo |
|---|---|
| `Dockerfile` | Build multi-stage produzione |
| `docker-compose.prod.yml` | App + Postgres + Redis (test locale o VPS) |
| `.env.production.example` | Template variabili runtime |
| `docker/` | nginx, supervisord, entrypoint, php.ini |

### Hosting che chiede solo un Dockerfile

1. Punta il builder alla root del repo (`Dockerfile`).
2. Espone la porta **80**.
3. Configura le variabili d’ambiente (minimo): `APP_KEY`, `APP_URL`, `FRONTEND_URL`, `DB_*`, `REDIS_*` (se usi Redis), `REVERB_*`, mail e Stripe.
4. Genera la chiave: `php artisan key:generate --show` (in locale) e incollala in `APP_KEY`.
5. `APP_URL` e `FRONTEND_URL` devono essere l’URL pubblico HTTPS del sito (stesso origin).
6. Il database Postgres/Redis va fornito dall’host (managed) oppure con `docker-compose.prod.yml`.

### Test locale dell’immagine

```bash
# dalla root del repo
cp .env.production.example .env.production
# compila APP_KEY, password DB, URL (es. http://localhost:8080)

docker compose -f docker-compose.prod.yml up -d --build
```

App: [http://localhost:8080](http://localhost:8080)  
Health: [http://localhost:8080/up](http://localhost:8080/up) e `/api/health`

Primo avvio con dati demo:

```bash
RUN_SEEDERS=true docker compose -f docker-compose.prod.yml up -d
```

### Note

- Le migration partono all’avvio (`RUN_MIGRATIONS=true` di default).
- WebSocket Reverb è in proxy su `/app` (come in Vite dev): lascia `VITE_REVERB_USE_PROXY=true` nel build.
- Storage persistente: volume `servio_storage` (compose) oppure volume montato su `/var/www/html/storage/app`.
- TLS termina di solito sul reverse proxy dell’host (Coolify, Traefik, nginx del provider): l’app ascolta HTTP sulla 80.

## Sicurezza MVP

- Rate limiting su login, ordini, waiter-call
- Sanctum token per staff
- Middleware `role:` per RBAC
- Audit log su CRUD e cambi stato ordine
