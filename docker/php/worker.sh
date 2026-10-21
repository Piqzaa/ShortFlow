#!/bin/sh
set -eu

cd /app

# A fresh checkout can start before Composer and migrations have finished.
# Do not run migrations or create database tables implicitly in the worker.
attempt=0
until [ -f vendor/autoload.php ] && php bin/console dbal:run-sql 'SELECT 1 FROM messenger_messages LIMIT 1' --no-debug --quiet >/dev/null 2>&1; do
    if [ "$attempt" -eq 0 ]; then
        echo '[worker] Waiting for Composer dependencies and database migrations.'
    fi
    attempt=$((attempt + 1))
    if [ "$attempt" -ge 60 ]; then
        echo '[worker] Startup unavailable; check dependencies, configuration and migrations.' >&2
        exit 1
    fi
    sleep 5
done

echo '[worker] Queue ready.'
exec php bin/console messenger:consume "${MESSENGER_QUEUE:-publications}" --time-limit=3600 --memory-limit=256M --no-debug --no-interaction
