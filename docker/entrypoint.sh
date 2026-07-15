#!/bin/bash
set -euo pipefail

APP_DIR=/var/www/html

if [ -L "$APP_DIR/.env" ] && [ ! -e "$APP_DIR/.env" ]; then
    rm -f "$APP_DIR/.env"
fi

if [ ! -f "$APP_DIR/.env" ] && [ -f "$APP_DIR/.env.example" ]; then
    cp "$APP_DIR/.env.example" "$APP_DIR/.env"
fi

HOME_DIR="${HOME:-$APP_DIR/.home}"
export HOME="$HOME_DIR"
mkdir -p "$HOME_DIR/.config/psysh" \
         "$APP_DIR/storage/framework"/{sessions,views,cache} \
         "$APP_DIR/storage/logs" \
         "$APP_DIR/storage/app/public" \
         "$APP_DIR/bootstrap/cache"
chmod -R ug+rwX "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" 2>/dev/null || true
if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" 2>/dev/null || true
fi

if [ -f "$APP_DIR/composer.json" ] && [ ! -f "$APP_DIR/vendor/autoload.php" ]; then
    composer install --no-interaction --prefer-dist
fi

if [ -f "$APP_DIR/artisan" ]; then
    if [ -z "${APP_KEY:-}" ] && grep -qE '^APP_KEY=$' "$APP_DIR/.env" 2>/dev/null; then
        php artisan key:generate --force || true
    fi

    php artisan config:clear || true

    if [ "${APP_ENV:-local}" = "local" ]; then
        php artisan route:clear || true
        php artisan view:clear || true
    fi

    if [ ! -L "$APP_DIR/public/storage" ]; then
        php artisan storage:link || true
    fi

    php artisan migrate --force || true
fi

exec "$@"
