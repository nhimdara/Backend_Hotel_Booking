#!/usr/bin/env sh
set -eu

RENDER_PORT="${PORT:-10000}"

php artisan config:cache
php artisan route:cache
php artisan view:cache

if ! php artisan migrate --force; then
    echo "WARNING: Database migration failed. Starting the web service so /api/health and Render logs remain available." >&2
fi

exec php artisan serve --host=0.0.0.0 --port="${RENDER_PORT}"
