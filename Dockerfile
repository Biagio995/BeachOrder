# ---- Frontend (Vue/Vite) ----
FROM node:20-alpine AS frontend

WORKDIR /app

COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci

COPY frontend/ ./

# Same-origin API + WebSocket via nginx reverse proxy
ARG VITE_API_URL=/api
ARG VITE_APP_NAME=Dalposto
ARG VITE_REVERB_USE_PROXY=true
ARG VITE_REVERB_APP_KEY=pcrxnxletfeemtelvhyn
ARG VITE_REVERB_HOST=
ARG VITE_REVERB_PORT=
ARG VITE_REVERB_SCHEME=https
ARG VITE_DEMO_TENANT=azure-beach
ARG VITE_DEMO_LOCATION=umbrella12

ENV VITE_API_URL=$VITE_API_URL \
    VITE_APP_NAME=$VITE_APP_NAME \
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
    --optimize-autoloader

COPY backend/ ./
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

# ---- Runtime (nginx + php-fpm + queue + reverb) ----
FROM php:8.2-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        curl \
        git \
        libpq-dev \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        nginx \
        supervisor \
        unzip \
        zip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        bcmath \
        gd \
        pcntl \
        pdo_pgsql \
        pgsql \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY backend/ ./
COPY --from=vendor /app/vendor ./vendor
COPY --from=frontend /app/dist /var/www/frontend

COPY docker/nginx/default.conf /etc/nginx/sites-available/default
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/php/local.ini /usr/local/etc/php/conf.d/zz-local.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh \
    && rm -f /etc/nginx/sites-enabled/default \
    && ln -s /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default \
    && mkdir -p \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache /var/www/frontend \
    && chmod -R ug+rwx storage bootstrap/cache

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    PHP_FPM_LISTEN=9000

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD curl -fsS http://127.0.0.1/up || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
