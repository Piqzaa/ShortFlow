# Minimum security release for CVE-2026-109618 and CVE-2026-109619.
FROM php:8.4.27-cli-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev libicu-dev libzip-dev unzip git ffmpeg && docker-php-ext-install pdo_pgsql intl zip opcache && rm -rf /var/lib/apt/lists/*
COPY uploads.ini /usr/local/etc/php/conf.d/uploads.ini
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
RUN docker-php-ext-install pcntl
WORKDIR /app
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public", "docker/php/router.php"]
