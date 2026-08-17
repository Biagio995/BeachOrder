# Backup e restore database

Servio esegue backup automatici del database con copia remota (S3/MinIO), cifratura, retention configurabile, log e alert in caso di errore.

## Configurazione

Variabili in `backend/.env`:

| Variabile | Default | Descrizione |
|-----------|---------|-------------|
| `BACKUP_ENABLED` | `true` | Abilita backup automatici |
| `BACKUP_FREQUENCY` | `daily` | `hourly`, `daily`, `weekly` |
| `BACKUP_RETENTION_DAYS` | `30` | Giorni di conservazione |
| `BACKUP_LOCAL_DISK` | `backups` | Disco locale di staging |
| `BACKUP_REMOTE_DISK` | `s3` | Disco remoto (non sul server DB) |
| `BACKUP_REMOTE_PATH` | `database` | Prefisso path su S3/MinIO |
| `BACKUP_ENCRYPT` | `true` | Cifratura AES-256 (via `APP_KEY`) |
| `BACKUP_ALERT_EMAIL` | — | Email alert fallimento |
| `BACKUP_ALERT_SLACK_WEBHOOK` | — | Webhook Slack opzionale |

Per MinIO locale (docker compose):

```bash
docker compose up -d
# .env: BACKUP_REMOTE_DISK=s3, AWS_* come in .env.example
```

## Scheduler (produzione)

Aggiungere al crontab del server:

```bash
* * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1
```

Il backup parte in base a `BACKUP_FREQUENCY`; la pulizia retention gira ogni giorno.

## Comandi manuali

```bash
# Esegui backup adesso
php artisan backup:run

# Elenco backup recenti
php artisan backup:list

# Ripristina l'ultimo backup completato
php artisan backup:restore --latest --force

# Ripristina per ID
php artisan backup:restore 42 --force

# Rimuovi backup scaduti (retention)
php artisan backup:cleanup
```

## Log

Ogni esecuzione crea una riga in `backup_logs` (filename, driver, size, checksum, remote path, status, errori).

I messaggi strutturati finiscono anche in `storage/logs/laravel.log`.

## Alert

Se un backup fallisce:

1. Log di livello `error`
2. Email a `BACKUP_ALERT_EMAIL` (o super admin / `MAIL_FROM_ADDRESS`)
3. Slack webhook se configurato

## Procedura di restore (produzione)

> **Attenzione:** il restore sovrascrive il database corrente. Mettere l'app in manutenzione prima di procedere.

1. Metti l'applicazione in manutenzione:
   ```bash
   php artisan down
   ```
2. Identifica il backup da ripristinare:
   ```bash
   php artisan backup:list
   ```
3. Verifica che il driver del backup corrisponda al database attivo (`sqlite`, `pgsql`, …).
4. Esegui il restore:
   ```bash
   php artisan backup:restore --latest --force
   # oppure
   php artisan backup:restore 42 --force
   ```
5. Riavvia queue/worker se presenti.
6. Togli la manutenzione:
   ```bash
   php artisan up
   ```
7. Verifica login, ordini e dati tenant su un campione di record.

### PostgreSQL

Richiede `pg_restore` installato sul server e credenziali in `.env` (`DB_*`).

### SQLite (sviluppo)

Copia il file `.sqlite` dal backup decriptato/decompresso.

## Test restore

Il test automatico `BackupRestoreTest` esegue un ciclo reale backup → modifica dati → restore → verifica integrità su SQLite file-based.

```bash
cd backend
php artisan test --filter=BackupRestoreTest
```

## Sicurezza

- I backup su disco locale sono solo staging temporaneo; la copia persistente è su storage remoto.
- Con `BACKUP_ENCRYPT=true` i file sono cifrati con la chiave applicativa (`APP_KEY`): conservarla in modo sicuro; senza di essa i backup non sono recuperabili.
- Limitare l'accesso al bucket S3/MinIO e ruotare le credenziali AWS periodicamente.
