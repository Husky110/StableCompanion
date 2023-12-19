#!/bin/sh
#echo "Called with [$1] [$2] [$3]"
cd /var/www & php artisan stablecompanion:aria-finish "$1" "$3"