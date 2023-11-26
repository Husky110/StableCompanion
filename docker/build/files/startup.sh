#!/bin/sh
if [ ! -f "/var/www/database/database.sqlite" ]; then
  touch /var/www/database/database.sqlite
fi
cd /var/www
composer update
php artisan migrate
php artisan stablecompanion:clear-civitaicache
php artisan stablecompanion:scan-for-files
sleep 10s
php artisan stablecompanion:resume-active-downloads