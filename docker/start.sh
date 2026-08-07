#!/usr/bin/env sh
set -eu

RENDER_PORT="${PORT:-10000}"

php artisan config:clear
php artisan route:cache
php artisan view:cache

if ! php artisan migrate --force; then
    echo "WARNING: Configured database is unavailable; starting with the SQLite fallback." >&2

    SQLITE_DATABASE="/var/www/html/database/database.sqlite"
    SQLITE_IS_NEW=false
    if [ ! -f "${SQLITE_DATABASE}" ]; then
        touch "${SQLITE_DATABASE}"
        SQLITE_IS_NEW=true
    fi

    export DB_CONNECTION=sqlite
    export DB_DATABASE="${SQLITE_DATABASE}"
    export DATABASE_URL=""

    php artisan config:clear
    php artisan migrate --force

    if [ "${SQLITE_IS_NEW}" = true ]; then
        php artisan db:seed --force
    fi
fi

php artisan config:cache

exec php artisan serve --host=0.0.0.0 --port="${RENDER_PORT}"
