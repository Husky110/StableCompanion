#!/bin/sh
if [ ! -f "/var/www/database/database.sqlite" ]; then
  touch /var/www/database/database.sqlite
fi
cd /var/www
composer update
if [ ! -f "/var/www/.env" ]; then
  cp .env.example .env
  php artisan key:generate
  echo "APP_URL="$URL >> .env
fi
php artisan migrate --force
php artisan stablecompanion:clear-civitaicache
php artisan stablecompanion:scan-for-files
sleep 10s
php artisan stablecompanion:resume-active-downloads