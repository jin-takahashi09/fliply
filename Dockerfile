# syntax=docker/dockerfile:1
#
# Fliply — Google Cloud Run production image
# composer.json allows php ^8.3, but locked Symfony 8.1.* packages require php >=8.4.1
# → runtime image uses PHP 8.4
# Laravel ^13 / Vite frontend build
#

# -----------------------------------------------------------------------------
# Stage 1: Vite frontend assets
# -----------------------------------------------------------------------------
FROM node:22-bookworm-slim AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY vite.config.js ./
COPY resources ./resources
COPY public ./public

RUN npm run build

# -----------------------------------------------------------------------------
# Stage 2: Composer vendor (no-dev)
# -----------------------------------------------------------------------------
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --no-scripts \
    --prefer-dist

COPY app ./app
COPY bootstrap ./bootstrap
COPY config ./config
COPY database ./database
COPY routes ./routes
COPY artisan ./

RUN composer dump-autoload --optimize --no-dev --no-interaction

# -----------------------------------------------------------------------------
# Stage 3: PHP Apache runtime for Cloud Run
# -----------------------------------------------------------------------------
FROM php:8.4-apache-bookworm

ENV PORT=8080

# System libs + PHP extensions required by Laravel + SQLite
# (mbstring/curl/openssl/pdo/tokenizer/xml/ctype/fileinfo ship with this image)
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libicu-dev \
        libsqlite3-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" \
        intl \
        opcache \
        pdo_sqlite \
        zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# OPcache defaults suitable for Cloud Run
RUN printf '%s\n' \
    'opcache.enable=1' \
    'opcache.memory_consumption=128' \
    'opcache.interned_strings_buffer=16' \
    'opcache.max_accelerated_files=10000' \
    'opcache.validate_timestamps=0' \
    > /usr/local/etc/php/conf.d/opcache-cloud-run.ini

WORKDIR /var/www/html

# Application source (secrets excluded via .dockerignore — never COPY .env)
COPY --chown=www-data:www-data . .

# Production vendor + built Vite assets
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=frontend --chown=www-data:www-data /app/public/build ./public/build

# Temporary env for artisan during image build only (removed before the layer finishes).
# Runtime secrets (APP_KEY, DEEPL_API_KEY, …) must come from Cloud Run env vars.
RUN cp .env.example .env \
    && php artisan key:generate --force --no-interaction \
    && php artisan package:discover --ansi --no-interaction \
    && mkdir -p database \
    && touch database/database.sqlite \
    && chown www-data:www-data database/database.sqlite \
    && php artisan migrate --force --no-interaction \
    && php artisan dictionary:import --no-interaction \
    && rm -f .env \
    && chown -R www-data:www-data storage bootstrap/cache database \
    && chmod -R ug+rwx storage bootstrap/cache database

# Apache document root = Laravel /public + AllowOverride for .htaccess
RUN sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf \
    && sed -ri 's!/var/www/!/var/www/html/public!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && printf '%s\n' \
        '<Directory /var/www/html/public>' \
        '    Options FollowSymLinks' \
        '    AllowOverride All' \
        '    Require all granted' \
        '</Directory>' \
        > /etc/apache2/conf-available/laravel-public.conf \
    && a2enconf laravel-public

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
