#!/bin/sh
set -e

cd /var/www/html

# Single public port. Hosts like Koyeb/Render/Fly inject $PORT; default 80 so
# the image also works where the host mandates a fixed port.
export PORT="${PORT:-80}"
# Internal Reverb port — never exposed, nginx proxies /app + /apps to it.
export REVERB_SERVER_PORT="${REVERB_SERVER_PORT:-6001}"
# Scheduler (artisan schedule:work) can be dropped on tiny hosts — the demo
# compose disables it; per-minute jobs are additionally gated in
# routes/console.php while DEMO_MODE=true.
export SCHEDULER_AUTOSTART="${SCHEDULER_ENABLED:-true}"
if [ "$SCHEDULER_AUTOSTART" = "false" ] || [ "$SCHEDULER_AUTOSTART" = "0" ]; then
  SCHEDULER_AUTOSTART="false"
else
  SCHEDULER_AUTOSTART="true"
fi
export SCHEDULER_AUTOSTART

# Render the nginx vhost from the template (no gettext/envsubst dependency).
sed -e "s/__LISTEN_PORT__/${PORT}/g" \
    -e "s/__REVERB_PORT__/${REVERB_SERVER_PORT}/g" \
    /etc/nginx/templates/default.conf.template > /etc/nginx/sites-available/default
rm -f /etc/nginx/sites-enabled/default
ln -s /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default
nginx -t

mkdir -p \
  storage/app/public \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs \
  bootstrap/cache

# SQLite demo DB on a (possibly ephemeral) volume path: the file is
# .dockerignore-d, so create it here at boot BEFORE migrations run.
DB_FILE=""
if [ "${DB_CONNECTION:-}" = "sqlite" ]; then
  DB_FILE="${DB_DATABASE:-database/database.sqlite}"
  case "$DB_FILE" in
    /*) ;;
    *) DB_FILE="/var/www/html/${DB_FILE}" ;;
  esac
  mkdir -p "$(dirname "$DB_FILE")"
  touch "$DB_FILE"
  echo "==> Using SQLite database at ${DB_FILE}"
fi

if [ -z "${APP_KEY:-}" ]; then
  echo "ERROR: APP_KEY is not set. Generate one with: php artisan key:generate --show"
  exit 1
fi

# Behind reverse proxy / TLS terminator
export TRUSTED_PROXIES="${TRUSTED_PROXIES:-*}"

php artisan storage:link --force >/dev/null 2>&1 || true

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
  echo "==> Running migrations"
  php artisan migrate --force
fi

if [ "${DEMO_SEED:-false}" = "true" ]; then
  # Demo campaign: seed ONLY the single demo venue (never the dev seeders),
  # so every (re)boot recreates the same tenant, codes and QR payloads.
  echo "==> Seeding demo venue only"
  php artisan db:seed --class=DemoSeeder --force
elif [ "${RUN_SEEDERS:-false}" = "true" ]; then
  echo "==> Running seeders"
  php artisan db:seed --force
fi

if [ "${DEMO_MODE:-false}" = "true" ]; then
  echo "==> Demo safety check (refuses boot on unsafe demo config)"
  php artisan demo:check --fail
fi

# Migrations/seeds ran as root: hand the SQLite file, storage and caches back
# to www-data (php-fpm, queue worker and Reverb run as www-data).
if [ -n "$DB_FILE" ] && [ -f "$DB_FILE" ]; then
  chown www-data:www-data "$DB_FILE" || true
  chmod 664 "$DB_FILE" || true
fi
chown -R www-data:www-data storage bootstrap/cache || true

echo "==> Caching config/routes/views"
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Starting services (nginx :${PORT}, reverb internal :${REVERB_SERVER_PORT}, scheduler: ${SCHEDULER_AUTOSTART})"
exec "$@"
