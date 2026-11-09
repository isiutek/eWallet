#!/usr/bin/env bash
# Perbarui aplikasi di VPS ke versi terbaru dari GitHub: sudo bash /var/www/ewallet/deploy/update.sh
set -euo pipefail
APP_DIR=/var/www/ewallet
BRANCH="${BRANCH:-main}"
cd "$APP_DIR"
export COMPOSER_ALLOW_SUPERUSER=1
php artisan down --retry=30 || true
git config --global --add safe.directory "$APP_DIR"
git fetch origin "$BRANCH" && git reset --hard "origin/$BRANCH"
composer install --no-dev --optimize-autoloader --no-interaction
npm ci && npm run build && rm -rf node_modules
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache && php artisan route:cache && php artisan view:cache
chown -R www-data:www-data "$APP_DIR"
PHP="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
systemctl restart "php${PHP}-fpm"
php artisan up
echo "Pembaruan selesai."
