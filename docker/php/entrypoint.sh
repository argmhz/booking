#!/usr/bin/env sh
set -e

cd /var/www/html

if [ ! -f .env ] && [ -f .env.example ]; then
  cp .env.example .env
fi

# Populate shared public volume from image on first boot / empty volume.
if [ ! -f public/build/manifest.json ] && [ -d /opt/app-public ]; then
  mkdir -p public
  cp -a /opt/app-public/. public/
fi

if [ ! -d vendor ]; then
  composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts
fi

# Rebuild package discovery cache for current install.
php artisan package:discover --ansi --no-interaction

exec "$@"
