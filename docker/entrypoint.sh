#!/bin/sh
set -e

cd /var/www/html

# public/build is bind-mounted from the host (for host nginx to serve
# directly), so the freshly compiled assets baked into the image must be
# copied over it here rather than relying on the image's own copy.
rm -rf public/build
cp -r /opt/frontend-build public/build

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_USERNAME="${DB_USERNAME:-root}"
DB_PASSWORD="${DB_PASSWORD:-}"

echo "Running composer install..."
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

echo "Waiting for database at ${DB_HOST}:${DB_PORT}..."
DB_WAIT_RETRIES=30
until php -r "new PDO('mysql:host=${DB_HOST};port=${DB_PORT}', '${DB_USERNAME}', '${DB_PASSWORD}');" >/dev/null 2>&1; do
    DB_WAIT_RETRIES=$((DB_WAIT_RETRIES - 1))
    if [ "$DB_WAIT_RETRIES" -le 0 ]; then
        echo "Database at ${DB_HOST}:${DB_PORT} did not become reachable in time." >&2
        exit 1
    fi
    sleep 2
done
echo "Database is up."

if [ -z "$APP_KEY" ]; then
    echo "No APP_KEY set, generating one..."
    php artisan key:generate --force
fi

php artisan migrate --force

# The demo user/tenant seeder is not idempotent (plain User::factory()->create),
# so this only succeeds meaningfully on a fresh database; later runs fail on
# the unique constraint and are ignored rather than crashing the container.
php artisan db:seed --force || true

if [ ! -L public/storage ]; then
    php artisan storage:link
fi

php artisan optimize:clear

# The commands above run as root, so any file they create under storage/
# (logs, cache, session files) comes out root-owned — even though the image
# chowns storage/ to www-data at build time. Re-assert it here so the
# php-fpm workers (running as www-data) can actually write to it.
chown -R www-data:www-data storage bootstrap/cache

exec "$@"
