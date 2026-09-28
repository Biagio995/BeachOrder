# ---- Frontend (Vue/Vite) ----
FROM node:20-alpine AS frontend

WORKDIR /app

COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci

COPY frontend/ ./

# Same-origin API + WebSocket via nginx reverse proxy
ARG VITE_API_URL=/api
ARG VITE_REVERB_USE_PROXY=true
ARG VITE_REVERB_APP_KEY=pcrxnxletfeemtelvhyn
ARG VITE_REVERB_HOST=
ARG VITE_REVERB_PORT=
ARG VITE_REVERB_SCHEME=https
ARG VITE_DEMO_TENANT=lido-azzurra
ARG VITE_DEMO_LOCATION=umbrella01

ENV VITE_API_URL=$VITE_API_URL \
    VITE_REVERB_USE_PROXY=$VITE_REVERB_USE_PROXY \
    VITE_REVERB_APP_KEY=$VITE_REVERB_APP_KEY \
    VITE_REVERB_HOST=$VITE_REVERB_HOST \
    VITE_REVERB_PORT=$VITE_REVERB_PORT \
    VITE_REVERB_SCHEME=$VITE_REVERB_SCHEME \
    VITE_DEMO_TENANT=$VITE_DEMO_TENANT \
    VITE_DEMO_LOCATION=$VITE_DEMO_LOCATION

RUN npm run build

# ---- PHP dependencies ----
FROM composer:2 AS vendor

WORKDIR /app

COPY backend/composer.json backend/composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --prefer-dist \
    --no-interaction \
    --optimize-autoloader \
    --ignore-platform-req=php

COPY backend/ ./
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

# ---- Runtime (nginx + php-fpm + optional queue + reverb, single container) ----
FROM php:8.4-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        curl \
        git \
        libpq-dev \
        libsqlite3-dev \
        default-libmysqlclient-dev \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        nginx \
        openssl \
        supervisor \
        unzip \
        zip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        bcmath \
        gd \
        pcntl \
        pdo_mysql \
        mysqli \
        pdo_pgsql \
        pdo_sqlite \
        pgsql \
        zip \
    && cd /tmp \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/* \
    && mkdir -p /var/www/pma /etc/nginx/snippets \
    && curl -fsSL https://github.com/vrana/adminer/releases/download/v4.8.1/adminer-4.8.1.php \
        -o /var/www/pma/index.php \
    && chown -R www-data:www-data /var/www/pma

WORKDIR /var/www/html

COPY backend/ ./
COPY --from=vendor /app/vendor ./vendor
COPY --from=frontend /app/dist /var/www/frontend

COPY docker/nginx/default.conf.template /etc/nginx/templates/default.conf.template
COPY docker/nginx/pma-enabled.conf /etc/nginx/snippets/pma-enabled.conf
COPY docker/nginx/pma-disabled.conf /etc/nginx/snippets/pma-disabled.conf
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/php/local.ini /usr/local/etc/php/conf.d/zz-local.ini
COPY docker/php/fpm-www.conf /usr/local/etc/php-fpm.d/zz-demo.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh \
    && ln -sf /etc/nginx/snippets/pma-disabled.conf /etc/nginx/snippets/pma.conf \
    && rm -f /etc/nginx/sites-enabled/default \
    && mkdir -p \
        /data \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data /data storage bootstrap/cache /var/www/frontend \
    && chmod -R ug+rwx /data storage bootstrap/cache

# Single-container demo defaults: one public $PORT (Render Free injects its
# own; default 10000), Reverb on loopback :8080, SQLite on an ephemeral path.
# Scheduler is intentionally omitted from supervisord (demo does not need it).
ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    PHP_FPM_LISTEN=9000 \
    PORT=10000 \
    REVERB_SERVER_PORT=8080 \
    DEMO_QUEUE_WORKER=true \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/data/demo.sqlite \
    DEMO_MODE=false \
    DEMO_SEED=false \
    RUN_MIGRATIONS=true \
    RUN_SEEDERS=false

EXPOSE 10000

HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD curl -fsS http://127.0.0.1:${PORT:-10000}/up || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
