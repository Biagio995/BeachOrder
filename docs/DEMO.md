# Demo pubblica — guida operativa

Demo online stabile per girare i video della campagna social (i QR finiscono nei video).
Un **singolo container** esegue web app PHP, Reverb, queue worker e scheduler, dietro
**una sola porta** (`$PORT`), con SQLite su volume. Non serve fare deploy da qui:
questa guida prepara build, env e QR; il deploy lo fa chi ha accesso all'host.

Il nome pubblico del prodotto è **Servio**: in demo non compare mai “Beach Order”
(verificato con ricerca su tutto il repo: zero occorrenze; branding, titoli, email OTP
e UI dicono Servio; la testata mostra il nome del locale dimostrativo “Lido Azzurra”,
come da white-label).

## 1. Cosa contiene la demo

- `DemoSeeder` (`backend/database/seeders/DemoSeeder.php`) — eseguibile da solo e
  idempotente (`php artisan db:seed --class=DemoSeeder`):
  - **un solo locale inventato**: “Lido Azzurra” (slug `lido-azzurra`), beach bar/lido,
    nessun dato di attività reali;
  - menu in **4 categorie, 16 prodotti** con prezzi realistici, **4 gruppi
    varianti** (es. farcitura cornetto, base poké, formato spritz, pane) e
    **4 gruppi aggiunte** (extra club sandwich, insalata, cappuccino, mojito),
    **tutti tradotti nelle 4 lingue supportate dall'app (it, en, de, el)** con le
    stesse mappe JSON usate dal catalogo reale (nomi, descrizioni, varianti, aggiunte, tag);
  - **8 postazioni** con QR stabile (`umbrella01…06`, `table01…02`);
  - **3 utenti**: admin del locale, staff cucina, staff sala — password da env
    (vedi sotto), mai scritte in chiaro.
- `php artisan demo:export-qr` — un PNG per postazione (`ombrellone-01.png`, …).
- `php artisan demo:check [--fail]` — verifica la modalità demo sicura.
- `DEMO_MODE=true` — pagamenti online spenti, stampanti/POS spenti, carte
  eventualmente configurate confinate in sandbox, traduzione automatica
  disattivata (il menu demo è curato nelle 4 lingue).

## 2. Variabili d'ambiente

File di esempio: `.env.demo.example` (copiare in `.env.demo`, **mai committare i segreti**).

| Variabile | Obbligatoria | Default | Note |
|---|---|---|---|
| `APP_KEY` | sì | — | `php artisan key:generate --show` |
| `APP_URL` / `PUBLIC_URL` / `FRONTEND_URL` | sì | — | URL HTTPS pubblico (uguali tra loro) |
| `PORT` | no | `80` | Koyeb/Render/Fly la iniettano da soli |
| `DEMO_MODE` | sì | `false` | `true` in demo |
| `DEMO_SEED` | no | `false` | `true` = seed demo all'avvio |
| `DEMO_TENANT_SLUG` | no | `lido-azzurra` | se cambiato, ricostruire il frontend se usi i default QR |
| `DEMO_PUBLIC_URL` | no | `PUBLIC_URL` → `FRONTEND_URL` → `APP_URL` | base URL stampata nei QR |
| `DEMO_ADMIN_EMAIL` / `DEMO_STAFF_EMAIL` / `DEMO_WAITER_EMAIL` | no | `admin@…` / `cucina@…` / `sala@lido-azzurra.demo` | login staff demo |
| `DEMO_ADMIN_PASSWORD` / `DEMO_STAFF_PASSWORD` / `DEMO_WAITER_PASSWORD` | **sì in produzione** | `password` **solo se `APP_ENV=local`** | fuori da `local`, se mancano vengono generate random e stampate nel log |
| `DEMO_QR_OUTPUT` | no | `storage/app/demo-qr` | cartella PNG del comando export |
| `SUPER_ADMIN_EMAIL` / `SUPER_ADMIN_NAME` / `SUPER_ADMIN_PASSWORD` | **sì in produzione** | `super@servio.test` / … / come le altre password demo | super admin creato dal DemoSeeder quando `DEMO_SEED=true` |
| `SCHEDULER_ENABLED` | no | `true` | `false` su host minuscoli (spegne il processo scheduler) |
| `SANCTUM_STATEFUL_DOMAINS` | sì (Koyeb) | — | hostname demo senza schema, es. `servio-demo.koyeb.app` |
| `MAIL_MAILER` / `MAIL_LOG_CHANNEL` | no | `log` / `stderr` in demo | email OTP nel log del container (vedi § Koyeb) |
| `BACKUP_ENABLED` | no | `false` in demo | niente backup su disco effimero |
| `POS_FORCE_STUB` | no | `true` in demo | POS sempre stub in demo |
| `MONITORING_FRONTEND_CHECK` | no | `false` in demo | niente probe frontend in demo |
| `DEMO_PRINTERS_ENABLED` / `DEMO_POS_ENABLED` | no | `false` | restano `false` sulla demo pubblica |
| `TRANSLATION_AUTO` | no | `false` (consigliato in demo) | in demo è comunque forzato off |
| Stripe / chiavi pagamento | — | — | **non servono**: i pagamenti online sono spenti a livello di prodotto |

## 3. Build e avvio

```bash
cp .env.demo.example .env.demo   # compila APP_KEY, URL, password demo
docker compose -f docker-compose.demo.yml --env-file .env.demo up -d --build
```

All'avvio il container, in ordine: rende il template nginx su `$PORT`, prepara
SQLite su `/data/demo.sqlite`, `migrate --force`, seed demo se `DEMO_SEED=true`,
`demo:check --fail` se `DEMO_MODE=true` (si rifiuta di partire con configurazione
demo non sicura), cache di config/route/view, poi supervisord (php-fpm, nginx,
queue, **Reverb su porta interna 6001**, scheduler salvo `SCHEDULER_ENABLED=false`).

Dietro un proxy TLS (o host PaaS) basta esporre `$PORT` in HTTPS: API (`/api`),
WebSocket Reverb (`/app`, `/apps`) e SPA sono sulla **stessa origine**
(il client usa `wss` sulla 443, nessun'altra porta da aprire).

Variante Postgres: imposta `DB_CONNECTION=pgsql` + host/credenziali in `.env.demo`
(il volume `/data` resta usato per storage; vedi anche `docker-compose.prod.yml`).

## 4. Deploy su Koyeb Free (un solo container, disco effimero)

Stesso `Dockerfile`/`docker/` della repo, nessun file dedicato. Su Koyeb Free
(512 MB, 0.1 vCPU, **senza volumi**) ogni riavvio azzera il DB e il seed lo
ricrea identico: tenant, codici QR (`umbrella01…`, `table01…`) e payload dei QR
restano validi dopo ogni riavvio. Ordini e chiamate di prova invece si perdono —
normale per la demo.

Passi:

1. Crea un Service di tipo **Web** dal repo GitHub, Dockerfile di default.
   Porta esposta: Koyeb inietta `$PORT` da solo (l'immagine ascolta `$PORT`,
   default 80).
2. **Build args** (Koyeb → Builder → build args; servono solo se cambi i default):
   - `VITE_REVERB_APP_KEY` = uguale a `REVERB_APP_KEY` (vedi sotto perché).
   - `VITE_DEMO_TENANT` / `VITE_DEMO_LOCATION` solo se cambi slug/codici.
3. **Env del Service** (valori esatti, sostituisci host e segreti):

   | Variabile | Valore |
   |---|---|
   | `APP_KEY` | `php artisan key:generate --show` (in locale) |
   | `APP_URL` | `https://servio-demo.koyeb.app` |
   | `FRONTEND_URL` | `https://servio-demo.koyeb.app` |
   | `PUBLIC_URL` | `https://servio-demo.koyeb.app` |
   | `SANCTUM_STATEFUL_DOMAINS` | `servio-demo.koyeb.app` |
   | `APP_ENV` / `APP_DEBUG` | `production` / `false` |
   | `LOG_CHANNEL` / `LOG_LEVEL` | `stderr` / `info` |
   | `DB_CONNECTION` / `DB_DATABASE` | `sqlite` / `/data/demo.sqlite` |
   | `SESSION_DRIVER` / `CACHE_STORE` / `QUEUE_CONNECTION` / `BROADCAST_CONNECTION` | `database` / `database` / `database` / `reverb` |
   | `REVERB_APP_ID` / `REVERB_APP_KEY` / `REVERB_APP_SECRET` | `269467` / chiave pubblica / **segreto generato da te** |
   | `REVERB_HOST` / `REVERB_PORT` / `REVERB_SCHEME` | `0.0.0.0` / `6001` / `http` |
   | `REVERB_SERVER_HOST` / `REVERB_SERVER_PORT` | `0.0.0.0` / `6001` |
   | `DEMO_MODE` / `DEMO_SEED` | `true` / `true` |
   | `DEMO_TENANT_SLUG` | `lido-azzurra` |
   | `DEMO_ADMIN/STAFF/WAITER_PASSWORD` + `SUPER_ADMIN_PASSWORD` | segreti forti (no default in produzione) |
   | `SCHEDULER_ENABLED` | `false` (host minuscolo) |
   | `MAIL_MAILER` / `MAIL_LOG_CHANNEL` | `log` / `stderr` |
   | `BACKUP_ENABLED` | `false` |
   | `POS_FORCE_STUB` | `true` |
   | `MONITORING_FRONTEND_CHECK` | `false` |
   | `DEMO_PRINTERS_ENABLED` / `DEMO_POS_ENABLED` | `false` / `false` |
   | `RUN_MIGRATIONS` / `RUN_SEEDERS` | `true` / `false` |

4. Health check Koyeb su path `/up` (impostato nel Dockerfile).
5. Anti-sleep: il workflow `.github/workflows/demo-keepalive.yml` fa GET di
   `/up` ogni 20 minuti. Imposta la variabile repo **`vars.DEMO_URL`**
   (Settings → Secrets and variables → Actions → Variables) con l'URL pubblico;
   se non è impostata, il job termina subito con successo senza pingare.
6. Dopo il deploy: apri `/t/lido-azzurra/q/umbrella01`, verifica menu + lingue,
   fai login staff e controlla `/kitchen`. Poi esporta i QR con
   `DEMO_PUBLIC_URL` = URL Koyeb e stampali per i video.

Perché `VITE_REVERB_APP_KEY` è un build arg: il frontend Vue viene compilato
nell'immagine e la chiave Reverb resta scritta nel JS. Deve coincidere con
`REVERB_APP_KEY` di runtime: se cambi la chiave, **ricostruisci** (su Koyeb:
redeploy con cache di build invalidata o cambio build arg).

Email OTP in demo: con `MAIL_MAILER=log` + `MAIL_LOG_CHANNEL=stderr` il testo
delle email (codici OTP per reset password staff) finisce nello **stdout del
container**, leggibile nel Log viewer di Koyeb. Il cliente non ha login (QR +
sessione anonima), quindi per le riprese non serve OTP: le password staff sono
quelle delle env. In locale, alternativa: `php artisan mail:test-otp ADDR --show-code`.

## 5. QR: generare e rigenerare

I QR puntano a `{DEMO_PUBLIC_URL}/t/lido-azzurra/q/{codice}` (es.
`…/t/lido-azzurra/q/umbrella01`). Il codice è **stabile** (`umbrella01`,
`table02`, …): il QR stampato non cambia mai, ogni scansione emette un token
monouso lato server.

```bash
# dentro il container (o in locale con stesso .env):
php artisan demo:export-qr
php artisan demo:export-qr --output=/tmp/qr-demo --size=640
php artisan demo:export-qr --tenant=lido-azzurra
```

I file (`ombrellone-01.png`, …, `tavolo-bar-02.png`) vanno stampati/esposti così
come sono: i video restano validi finché non cambia `DEMO_PUBLIC_URL` o lo slug
del tenant. **Se cambia l'URL pubblico, i QR vanno rigenerati e ristampati.**

## 6. Flusso demo (per le riprese)

1. Cliente: scansiona QR → menu → selettore lingua (IT/EN/DE/EL) → carrello
   (variante/aggiunta) → ordine → pagina stato (si aggiorna da sola via Reverb).
   Si paga al locale: nessuna proposta di pagamento online in UI.
2. Chiamata cameriere dal menu (`/call-waiter`, con motivo: Conto, Acqua, …).
3. Staff: login cucina (`/kitchen`) e bar (`/bar`) → board live distinte per
   reparto → avanza stati `received → accepted → preparing → ready`;
   sala (`/waiter`) → chiamate con motivo visibile.
4. Admin: back-office menu/postazioni/utenti (`/admin`).

## 7. Report funzioni — verificato sull'app in esecuzione

Ambiente di verifica: backend Laravel + Reverb + frontend Vite avviati in locale,
stesso seed della demo; controlli eseguiti **nel browser** (screenshot) e via API.

- **(a) Menu tradotto nelle 4 lingue lato cliente con selettore funzionante — SÌ.**
  Evidenza: selettore bandiere IT/EN/DE/EL nella app bar; dopo ogni cambio il menu
  si ricarica tradotto — IT “Caffetteria e colazioni / Cornetto artigianale”,
  EN “Coffee & breakfast / Croissant”, DE “Kaffee & Frühstück”, EL “Καφές & πρωινό /
  Κρουασάν” (anche varianti/aggiunte, es. “Formato/Size/Größe/Μέγεθος”).
  Via API: `GET /api/t/lido-azzurra/menu?locale={it,en,de,el}` 200 in <0.3 s.
- **(b) Ordini smistati su schermi separati cucina e bar — SÌ.**
  Evidenza: route distinte `/kitchen` e `/bar` (stessa board filtrata per reparto);
  su un ordine misto la board cucina mostra solo “1× Club sandwich”
  (ordine BO-260926-EMAZI, Ombrellone 03) e la board bar solo “1× Spritz Aperol”.
- **(c) Chiamata cameriere con motivo visibile allo staff — SÌ.**
  Evidenza: pagina cliente con motivi (Conto, Acqua, Assistenza, …); inviando
  “Conto” da Ombrellone 01, la board sala mostra la chiamata “in attesa” con
  motivo visibile (“Λογαριασμός” con UI in greco). Nota: la pagina
  `/call-waiter` richiede il contesto postazione (va aperta dopo il menu via QR).

## 8. Cosa non funziona / limiti in demo (onesto)

- **Pagamenti online spenti**: disattivati a livello di prodotto dal 18/08
  (`PaymentService::ONLINE_METHODS` vuoto, `online_payments_enabled=false`);
  il checkout è “paga al locale” e nessuna UI propone carte/online. Nessuna env
  di pagamento serve per la demo.
- **Stampanti e POS spenti**: nessun tentativo di connessione TCP/esterna
  (`demo:check` lo conferma; il test stampa risponde “Printing is disabled in
  demo mode”). Scontrini fiscali e invii POS non avvengono.
- **Traduzione automatica spenta**: testi curati nel seeder nelle 4 lingue;
  nessun contenuto generato a runtime, nessuna dipendenza HTTP esterna nel menu.
- **SQLite**: perfetto per riprese e traffico demo, ma niente concorrenza
  elevata; backup = snapshot del volume `/data`. Postgres supportato via env.
- **Una sola istanza**: Reverb e code sono in-container; non scalare a N repliche
  (i WS devono restare sullo stesso nodo).
- **Mail finta**: nessun invio reale (impostare `MAIL_*` se servono OTP/reset;
  l'oggetto OTP dice comunque “Servio — …”).
- **Login throttling**: 10 tentativi/min su `/login` — nelle prove ravvicinate si
  prende 429, è normale.
- **Abbonamenti/billing, backup schedulati, POS mapping, analytics avanzate**:
  presenti nel codice ma fuori perimetro demo. Il tenant demo (`is_demo=true`)
  è esente dal gate abbonamento, quindi tutte le rotte staff/admin del §5
  funzionano senza pagamenti.
- **Chiave Reverb compilata nel frontend**: cambio `REVERB_APP_KEY` = rebuild.
- **Credenziali demo**: sono utenze dimostrative con password condivise al team
  video — ruotarle dopo la campagna e non riusarle in produzione.
