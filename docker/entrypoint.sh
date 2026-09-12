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

# Only re-run composer install when composer.lock actually changed (e.g. a new
# package was added on the host), since vendor/ persists across restarts in
# its own volume and a full install on every boot would be wasted work.
LOCK_HASH_FILE="vendor/.composer-lock-hash"
CURRENT_LOCK_HASH=$(md5sum composer.lock 2>/dev/null | cut -d' ' -f1)

if [ ! -f "$LOCK_HASH_FILE" ] || [ "$(cat "$LOCK_HASH_FILE" 2>/dev/null)" != "$CURRENT_LOCK_HASH" ]; then
    echo "composer.lock changed, running composer install..."
    composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
    echo "$CURRENT_LOCK_HASH" > "$LOCK_HASH_FILE"
else
    echo "composer.lock unchanged, skipping composer install."
fi

echo "Waiting for database at ${DB_HOST}:${DB_PORT}..."
until php -r "new PDO('mysql:host=${DB_HOST};port=${DB_PORT}', '${DB_USERNAME}', '${DB_PASSWORD}');" >/dev/null 2>&1; do
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

exec "$@"
