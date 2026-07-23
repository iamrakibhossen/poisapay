# syntax=docker/dockerfile:1
# PoisaPay production image — multi-stage: build assets, then a lean PHP-FPM runtime.

# ---- Stage 1: front-end assets (Vite + Tailwind) ----
FROM node:20-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

# ---- Stage 2: PHP dependencies (no dev) ----
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
# --no-scripts: artisan isn't available until app code is copied; scripts run at runtime.
RUN composer install --no-dev --no-scripts --prefer-dist --no-interaction --optimize-autoloader --no-progress

# ---- Stage 3: runtime ----
FROM php:8.3-fpm-bookworm AS runtime

# System + PHP extensions. predis is used for Redis, so no ext-redis needed.
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN install-php-extensions \
      pdo_pgsql \
      bcmath \
      gmp \
      intl \
      pcntl \
      zip \
      opcache \
    && apt-get update \
    && apt-get install -y --no-install-recommends postgresql-client \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# Copy application code, then vendored deps and built assets from earlier stages.
COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

# Finish composer lifecycle now that artisan exists, and cache framework state.
RUN composer dump-autoload --optimize --no-dev --no-interaction \
    && php artisan package:discover --ansi || true

# PHP config
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-poisapay.ini

# Permissions for Laravel writable dirs
RUN chown -R www-data:www-data storage bootstrap/cache

USER www-data
EXPOSE 9000
CMD ["php-fpm"]
