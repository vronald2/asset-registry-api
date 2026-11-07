#!/bin/sh
# Prepares the bind-mounted Laravel application on every container start, then runs the container command
# (php-fpm by default). Every step is idempotent, so restarting the container is always safe.
set -eu

READY_FLAG=/tmp/app-ready
rm -f "$READY_FLAG"

if [ ! -f .env ]; then
    echo "app-entrypoint: creating .env from .env.example"
    cp .env.example .env
fi

composer install --no-interaction --no-progress --prefer-dist

if ! grep -Eq '^APP_KEY=.+' .env; then
    php artisan key:generate --force --no-interaction
fi

# The database schema is built from the migrations (the db service is healthy before this container starts,
# the retry only covers a slow first start of MySQL).
attempt=1
until php artisan migrate --force --no-interaction; do
    if [ "$attempt" -ge 10 ]; then
        echo "app-entrypoint: migrations failed" >&2
        exit 1
    fi
    attempt=$((attempt + 1))
    sleep 3
done

# Read by the healthcheck in docker-compose.yaml.
touch "$READY_FLAG"

exec "$@"
