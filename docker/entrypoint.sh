#!/bin/sh
set -eu

mkdir -p \
    storage/app/public \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

if [ -z "${APP_KEY:-}" ]; then
    if [ "${APP_ENV:-local}" = "production" ]; then
        echo "APP_KEY is required in production." >&2
        exit 1
    fi

    APP_KEY="$(php artisan key:generate --show --no-ansi)"
    export APP_KEY
fi

php artisan storage:link --relative >/dev/null 2>&1 || true

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
fi

if [ "${APP_ENV:-local}" = "production" ]; then
    php artisan config:cache
    php artisan event:cache
    php artisan view:cache
fi

exec "$@"
