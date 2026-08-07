#!/usr/bin/env sh
set -eu

# Render provides the public port at runtime; Apache defaults to port 80.
RENDER_PORT="${PORT:-10000}"
sed -ri "s/Listen 80/Listen ${RENDER_PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:80>/<VirtualHost *:${RENDER_PORT}>/" /etc/apache2/sites-available/000-default.conf

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force

exec apache2-foreground
