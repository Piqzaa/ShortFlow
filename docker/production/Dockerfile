FROM node:22-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY assets/ assets/
COPY public/ public/
RUN npm run build

FROM composer:2 AS composer

FROM php:8.4.27-apache-bookworm
ENV APP_ENV=prod APP_DEBUG=0
WORKDIR /app
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libicu-dev unzip ffmpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql intl opcache pcntl \
    && rm -rf /var/lib/apt/lists/* \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/production/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini
COPY docker/production/runtime.ini /usr/local/etc/php/conf.d/runtime.ini
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --prefer-dist --no-interaction --no-progress
COPY .env ./
COPY bin/ bin/
COPY config/ config/
COPY migrations/ migrations/
COPY src/ src/
COPY templates/ templates/
COPY public/ public/
COPY --from=frontend /app/public/build/ public/build/
COPY docker/php/worker.sh docker/php/worker.sh
COPY docker/production/check-storage.sh docker/production/check-storage.sh
RUN sed -i 's/\r$//' docker/php/worker.sh docker/production/check-storage.sh \
    && composer dump-autoload --no-dev --classmap-authoritative --no-interaction \
    && mkdir -p var/cache var/log var/storage var/sessions \
    && chown -R www-data:www-data var \
    && apache2ctl configtest
