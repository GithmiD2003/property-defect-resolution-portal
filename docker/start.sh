#!/bin/sh
set -eu

: "${APP_KEY:?APP_KEY must be configured in hosting settings}"

php artisan config:cache
php artisan view:cache

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
fi

chown -R www-data:www-data storage bootstrap/cache

exec apache2-foreground
