#!/bin/sh
set -e

cd /var/www/html

# Single public port (hosts like Koyeb/Render/Fly inject $PORT).
export PORT="${PORT:-8080}"
# Internal Reverb port — never exposed, nginx proxies /app + /apps to it.
export REVERB_SERVER_PORT="${REVERB_SERVER_PORT:-6001}"

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

chown -R www-data:www-data storage bootstrap/cache || true

if [ -z "${APP_KEY:-}" ]; then
  echo "ERROR: APP_KEY is not set. Generate one with: php artisan key:generate --show"
  exit 1
fi

# Behind reverse proxy / TLS terminator
export TRUSTED_PROXIES="${TRUSTED_PROXIES:-*}"

# SQLite demo DB on a volume path: make sure the file exists and is writable.
if [ "${DB_CONNECTION:-}" = "sqlite" ]; then
  DB_FILE="${DB_DATABASE:-database/database.sqlite}"
  case "$DB_FILE" in
    /*) ;;
    *) DB_FILE="/var/www/html/${DB_FILE}" ;;
  esac
  mkdir -p "$(dirname "$DB_FILE")"
  touch "$DB_FILE"
  chown www-data:www-data "$DB_FILE" || true
  echo "==> Using SQLite database at ${DB_FILE}"
fi

php artisan storage:link --force >/dev/null 2>&1 || true

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
  echo "==> Running migrations"
  php artisan migrate --force
fi

if [ "${RUN_SEEDERS:-false}" = "true" ]; then
  echo "==> Running seeders"
  php artisan db:seed --force
fi

if [ "${DEMO_SEED:-false}" = "true" ]; then
  echo "==> Seeding demo venue"
  php artisan db:seed --class=DemoSeeder --force
fi

if [ "${DEMO_MODE:-false}" = "true" ]; then
  echo "==> Demo safety check (refuses boot on unsafe demo config)"
  php artisan demo:check --fail
fi

echo "==> Caching config/routes/views"
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Starting services (nginx :${PORT}, reverb internal :${REVERB_SERVER_PORT})"
exec "$@"
