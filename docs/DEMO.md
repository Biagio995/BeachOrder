# Demo pubblica — guida operativa

Demo online stabile per girare i video della campagna social (i QR finiscono nei video).
Un **singolo container** esegue web app PHP, Reverb e (opzionale) queue worker, dietro
**una sola porta** (`$PORT`), con SQLite su filesystem effimero. Non serve fare deploy da qui:
questa guida prepara build, env e QR; il deploy su Render Free lo fai tu via API.

Il nome prodotto pubblico **non è hardcoded** in questo PR: arriva da `APP_NAME` /
`VITE_APP_NAME` al deploy (dopo il merge dei PR di rebrand). In demo non compare mai
un brand inventato nei default: email e host di esempio usano solo domini fittizi
(`*.demo`, `demo.example`). La testata cliente mostra il nome del locale dimostrativo
“Lido Azzurra” (white-label del tenant).

## 1. Cosa contiene la demo

- `DemoSeeder` (`backend/database/seeders/DemoSeeder.php`) — eseguibile da solo e
  idempotente (`php artisan db:seed --class=DemoSeeder`):
  - **un solo locale inventato**: “Lido Azzurra” (slug `lido-azzurra`), beach bar/lido,
    nessun dato di attività reali;
  - menu in **4 categorie, 16 prodotti** con prezzi realistici, **4 gruppi
    varianti** e **4 gruppi aggiunte**, **tutti tradotti nelle 4 lingue supportate
    dall'app (it, en, de, el)**;
  - **8 postazioni** con QR stabile (`umbrella01…06`, `table01…02`);
  - **4 utenti staff**: admin, cucina (`staff_position=kitchen`), bar
    (`staff_position=bar`), sala (`staff_position=waiter`) — password **solo da env**,
    mai scritte in chiaro. Senza `staff_position` le board cucina/bar rispondono 403.
- `php artisan demo:export-qr` — un PNG per postazione (`ombrellone-01.png`, …).
- `php artisan demo:check [--fail]` — verifica la modalità demo sicura.
- `DEMO_MODE=true` — stampanti/POS spenti, traduzione automatica disattivata
  (il menu demo è curato nelle 4 lingue). **Pagamenti online spenti a livello di
  prodotto** (nessuna env Stripe/Nexi necessaria).

Con `DEMO_MODE` / `DEMO_SEED` spenti (default) il flusso normale di sviluppo e
produzione resta invariato.

## 2. Variabili d'ambiente

File di esempio: `.env.demo.example` (copiare in `.env.demo`, **mai committare i segreti**).

| Variabile | Obbligatoria | Default | Note |
|---|---|---|---|
| `APP_NAME` | no | (runtime) | Nome prodotto backend; impostalo al deploy |
| `VITE_APP_NAME` | no | (build arg) | Nome prodotto frontend / `<title>`; rebuild se cambia |
| `APP_KEY` | sì | — | `php artisan key:generate --show` |
| `APP_URL` / `PUBLIC_URL` / `FRONTEND_URL` | sì | — | URL HTTPS pubblico (uguali tra loro) |
| `PORT` | no | `10000` | Render la inietta da solo |
| `DEMO_MODE` | sì | `false` | `true` in demo |
| `DEMO_SEED` | no | `false` | `true` = seed demo all'avvio (idempotente) |
| `DEMO_TENANT_SLUG` | no | `lido-azzurra` | se cambiato, ricostruire il frontend se usi i default QR |
| `DEMO_PUBLIC_URL` | no | `PUBLIC_URL` → `FRONTEND_URL` → `APP_URL` | base URL stampata nei QR |
| `DEMO_ADMIN_EMAIL` / `DEMO_STAFF_EMAIL` / `DEMO_BAR_EMAIL` / `DEMO_WAITER_EMAIL` | no | `admin@…` / `cucina@…` / `bar@…` / `sala@lido-azzurra.demo` | login staff demo |
| `DEMO_ADMIN_PASSWORD` / `DEMO_STAFF_PASSWORD` / `DEMO_BAR_PASSWORD` / `DEMO_WAITER_PASSWORD` | **sì in produzione** | `password` **solo se `APP_ENV=local`** | fuori da `local`, se mancano vengono generate random e stampate nel log |
| `SUPER_ADMIN_EMAIL` / `SUPER_ADMIN_NAME` / `SUPER_ADMIN_PASSWORD` | **sì in produzione** | `super@demo.example` / … | super admin creato dal DemoSeeder quando `DEMO_SEED=true` |
| `QUEUE_CONNECTION` | no | `database` | usa `sync` + `DEMO_QUEUE_WORKER=false` per risparmiare RAM |
| `DEMO_QUEUE_WORKER` | no | `true` | `false` spegne il processo `queue:work` in supervisord |
| `REVERB_APP_ID` / `REVERB_APP_KEY` / `REVERB_APP_SECRET` | sì | — | chiave pubblica compilata anche come build arg `VITE_REVERB_APP_KEY` |
| `REVERB_SERVER_HOST` / `REVERB_SERVER_PORT` | no | `127.0.0.1` / `8080` | Reverb solo su loopback; nginx fa proxy di `/app` |
| `SANCTUM_STATEFUL_DOMAINS` | sì (Render) | — | hostname demo senza schema, es. `your-app.onrender.com` |
| `MAIL_MAILER` / `MAIL_LOG_CHANNEL` | no | `log` / `stderr` in demo | email OTP nel log del container |
| `BACKUP_ENABLED` | no | `false` in demo | niente backup su disco effimero |
| `POS_FORCE_STUB` | no | `true` in demo | POS sempre stub in demo |
| `MONITORING_FRONTEND_CHECK` | no | `false` in demo | niente probe frontend in demo |
| `DEMO_PRINTERS_ENABLED` / `DEMO_POS_ENABLED` | no | `false` | restano `false` sulla demo pubblica |
| Stripe / chiavi pagamento | — | — | **non servono**: i pagamenti online sono spenti a livello di prodotto |

### Utenti demo (ruoli / posizioni — nessuna password qui)

| Email (default) | Ruolo | `staff_position` | Schermo |
|---|---|---|---|
| `SUPER_ADMIN_EMAIL` | `super_admin` | — | piattaforma |
| `DEMO_ADMIN_EMAIL` | `admin` | — | `/admin` |
| `DEMO_STAFF_EMAIL` | `staff` | `kitchen` | `/kitchen` |
| `DEMO_BAR_EMAIL` | `staff` | `bar` | `/bar` |
| `DEMO_WAITER_EMAIL` | `staff` | `waiter` | `/waiter` |

## 3. Build e avvio

```bash
cp .env.demo.example .env.demo   # compila APP_KEY, URL, password demo
docker compose -f docker-compose.demo.yml --env-file .env.demo up -d --build
```

All'avvio il container, in ordine: rende il template nginx su `$PORT`, prepara
SQLite su `/data/demo.sqlite`, `migrate --force`, seed demo se `DEMO_SEED=true`,
`demo:check --fail` se `DEMO_MODE=true`, cache di config/route/view, poi
supervisord (php-fpm ondemand max 2, nginx, **Reverb su 127.0.0.1:8080**,
queue worker se `DEMO_QUEUE_WORKER=true`). **Niente scheduler** nel container demo
(non serve per le riprese).

Dietro il TLS di Render basta esporre `$PORT` in HTTPS: API (`/api`),
WebSocket Reverb (`/app`, `/apps`, upgrade, `proxy_read_timeout` ≥ 300s) e SPA
sono sulla **stessa origine**.

## 4. Deploy su Render Free (un solo container, disco effimero)

Stesso `Dockerfile`/`docker/` della repo. Su Render Free (512 MB, 0.1 vCPU,
disco effimero) ogni riavvio azzera il DB e il seed lo ricrea identico: tenant,
codici QR e payload restano validi. Ordini e chiamate di prova si perdono —
normale per la demo.

1. Crea un **Web Service** Docker (Frankfurt), porta = `$PORT` (default immagine 10000).
2. **Build args**: `VITE_REVERB_APP_KEY` = uguale a `REVERB_APP_KEY`;
   `VITE_APP_NAME` / `VITE_DEMO_*` solo se li cambi rispetto ai default del branch
   di deploy.
3. Imposta le env della tabella §2 (password demo forti; `DEMO_MODE=true`,
   `DEMO_SEED=true`, `MAIL_MAILER=log`, `BACKUP_ENABLED=false`,
   `POS_FORCE_STUB=true`, `MONITORING_FRONTEND_CHECK=false`).
4. Health check su `/up`.
5. Anti-sleep: workflow `.github/workflows/demo-keepalive.yml` (cron ogni 5 min
   `3-59/5`) + backup esterno (cron-job.org). Imposta `vars.DEMO_URL`.
6. Dopo il deploy: apri `/t/lido-azzurra/q/umbrella01`, verifica menu + lingue,
   login staff `/kitchen` e `/bar`, poi esporta i QR.

### Render Free limits

- **Sleep** dopo ~15 min di idle; cold start ~1 minuto.
- **Keep-alive**: workflow GitHub ogni 5 minuti + cron esterno di backup
  (cron-job.org) sullo stesso `/up`.
- **Regola riprese**: apri l’URL **2 minuti prima** di filmare / inviare link.
- **Dati**: reset a ogni restart (by design, reseed idempotente).
- **Quota**: 750 instance-hours/mese; **un solo** servizio free always-on per workspace.
- **Outbound**: 5 GB/mese.
- **Fallback a pagamento (non abilitato)**: Render `0.5c-512mb` ~$7/mese (no sleep;
  disco opzionale ~$0.25/GB/mese). Non abilitare da questa guida.

Perché `VITE_REVERB_APP_KEY` è un build arg: il frontend viene compilato
nell'immagine. Deve coincidere con `REVERB_APP_KEY` di runtime: se cambi la
chiave, **ricostruisci**.

Email OTP in demo: con `MAIL_MAILER=log` + `MAIL_LOG_CHANNEL=stderr` i codici
finiscono nello stdout del container (log Render). Il cliente non ha login
(QR + sessione anonima).

## 5. QR: generare e rigenerare

I QR puntano a `{DEMO_PUBLIC_URL}/t/lido-azzurra/q/{codice}` (es.
`…/t/lido-azzurra/q/umbrella01`). Il codice è **stabile**.

```bash
# dentro il container (o in locale con stesso .env):
php artisan demo:export-qr
php artisan demo:export-qr --output=/tmp/qr-demo --size=640
php artisan demo:export-qr --tenant=lido-azzurra
```

Output di default: `storage/app/demo-qr/` (o `DEMO_QR_OUTPUT`). File tipici:
`ombrellone-01.png` … `ombrellone-06.png`, `tavolo-bar-01.png`, `tavolo-bar-02.png`.
**Se cambia l'URL pubblico, i QR vanno rigenerati e ristampati.** Non committare i PNG.

## 6. Flusso demo (per le riprese)

1. Cliente: scansiona QR → menu → selettore lingua (IT/EN/DE/EL) → carrello
   (variante/aggiunta) → ordine → pagina stato (aggiornamento via Reverb).
   Si paga al locale: nessuna proposta di pagamento online in UI.
2. Chiamata cameriere dal menu (`/call-waiter`, con motivo: Conto, Acqua, …).
3. Staff: login cucina (`/kitchen`) e bar (`/bar`) → board live distinte per
   reparto → avanza stati `received → accepted → preparing → ready`;
   sala (`/waiter`) → chiamate con motivo visibile.
4. Admin: back-office menu/postazioni/utenti (`/admin`).

## 7. Funzioni attese in ripresa

- **(a)** Menu tradotto nelle 4 lingue lato cliente con selettore funzionante.
- **(b)** Ordini smistati su schermi separati cucina e bar.
- **(c)** Chiamata cameriere con motivo già indicato, visibile allo staff.

## 8. Cosa non funziona / limiti in demo (onesto)

- **Pagamenti online spenti** a livello di prodotto; checkout “paga al locale”.
- **Stampanti e POS spenti** (`demo:check` lo conferma).
- **Traduzione automatica spenta**: testi curati nel seeder.
- **SQLite** + **una sola istanza** (Reverb/code in-container).
- **Mail finta** (`MAIL_MAILER=log`).
- **Login throttling**: 10 tentativi/min su `/login`.
- **Chiave Reverb compilata nel frontend**: cambio = rebuild.
- **Credenziali demo**: utenze dimostrative — ruotarle dopo la campagna.
