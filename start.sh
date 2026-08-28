#!/bin/bash
set -e

PORT="${PORT:-80}"

sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/\*:80/*:${PORT}/" /etc/apache2/sites-available/000-default.conf

php artisan migrate --force
php artisan config:cache
php artisan route:cache

exec apache2-foreground
