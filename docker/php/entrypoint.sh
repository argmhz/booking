#!/usr/bin/env sh
set -e

cd /var/www/html

if [ ! -f .env ] && [ -f .env.example ]; then
  cp .env.example .env
fi

if [ ! -d vendor ]; then
  composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts
fi

# Avoid stale provider cache from local/dev builds.
rm -f bootstrap/cache/packages.php bootstrap/cache/services.php
php artisan package:discover --ansi --no-interaction

exec "$@"
