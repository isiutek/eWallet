#!/usr/bin/env bash
# Instalasi Dompetku di VPS Ubuntu 22.04 / 24.04 (jalankan sebagai root).
#
#   curl -fsSL https://raw.githubusercontent.com/isiutek/eWallet/main/deploy/install.sh -o install.sh
#   sudo bash install.sh dompet.domainanda.com admin@domainanda.com
#
# Argumen 1: domain (atau IP VPS jika belum punya domain; HTTPS dilewati)
# Argumen 2: email admin aplikasi & email untuk sertifikat SSL Let's Encrypt
set -euo pipefail

DOMAIN="${1:?Pakai: bash install.sh <domain-atau-ip> <email-admin>}"
ADMIN_EMAIL="${2:?Pakai: bash install.sh <domain-atau-ip> <email-admin>}"
REPO="${REPO:-https://github.com/isiutek/eWallet.git}"
BRANCH="${BRANCH:-main}"
APP_DIR=/var/www/ewallet
PHP=8.3
DB_NAME=ewallet
DB_USER=ewallet

[ "$(id -u)" -eq 0 ] || { echo "Jalankan sebagai root (sudo)."; exit 1; }
export DEBIAN_FRONTEND=noninteractive

echo "==> Memasang paket sistem"
apt-get update -y
apt-get install -y software-properties-common ca-certificates curl gnupg git unzip ufw nginx postgresql
if ! apt-cache show php${PHP}-fpm >/dev/null 2>&1; then
    add-apt-repository -y ppa:ondrej/php
    apt-get update -y
fi
apt-get install -y php${PHP}-fpm php${PHP}-cli php${PHP}-pgsql php${PHP}-mbstring php${PHP}-xml php${PHP}-curl \
    php${PHP}-zip php${PHP}-gd php${PHP}-bcmath php${PHP}-intl
if ! command -v composer >/dev/null; then
    curl -fsSL https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi
if ! command -v node >/dev/null || [ "$(node -v | cut -c2- | cut -d. -f1)" -lt 18 ]; then
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
    apt-get install -y nodejs
fi

echo "==> Menyiapkan database PostgreSQL"
DB_PASS_FILE=/root/.ewallet-db-password
if [ ! -f "$DB_PASS_FILE" ]; then
    openssl rand -hex 24 > "$DB_PASS_FILE"; chmod 600 "$DB_PASS_FILE"
fi
DB_PASS=$(cat "$DB_PASS_FILE")
sudo -u postgres psql -tAc "SELECT 1 FROM pg_roles WHERE rolname='${DB_USER}'" | grep -q 1 \
    || sudo -u postgres psql -c "CREATE USER ${DB_USER} WITH PASSWORD '${DB_PASS}';"
sudo -u postgres psql -tAc "SELECT 1 FROM pg_database WHERE datname='${DB_NAME}'" | grep -q 1 \
    || sudo -u postgres psql -c "CREATE DATABASE ${DB_NAME} OWNER ${DB_USER};"

echo "==> Mengambil kode aplikasi"
if [ -d "$APP_DIR/.git" ]; then
    git -C "$APP_DIR" fetch origin "$BRANCH" && git -C "$APP_DIR" reset --hard "origin/$BRANCH"
else
    git clone -b "$BRANCH" "$REPO" "$APP_DIR"
fi
cd "$APP_DIR"

FIRST_INSTALL=0
if [ ! -f .env ]; then
    FIRST_INSTALL=1
    if [[ "$DOMAIN" =~ ^[0-9.]+$ ]]; then SCHEME=http; else SCHEME=https; fi
    cp .env.example .env
    sed -i "s|^APP_ENV=.*|APP_ENV=production|; s|^APP_DEBUG=.*|APP_DEBUG=false|; s|^APP_URL=.*|APP_URL=${SCHEME}://${DOMAIN}|" .env
    sed -i "s|^DB_HOST=.*|DB_HOST=127.0.0.1|; s|^DB_DATABASE=.*|DB_DATABASE=${DB_NAME}|; s|^DB_USERNAME=.*|DB_USERNAME=${DB_USER}|; s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASS}|" .env
    sed -i "s|^LOG_LEVEL=.*|LOG_LEVEL=warning|" .env
    if [ "$SCHEME" = https ]; then echo "SESSION_SECURE_COOKIE=true" >> .env; fi
fi

echo "==> Memasang dependensi & build aset"
export COMPOSER_ALLOW_SUPERUSER=1
composer install --no-dev --optimize-autoloader --no-interaction
npm ci && npm run build && rm -rf node_modules
[ "$FIRST_INSTALL" -eq 1 ] && php artisan key:generate --force

ADMIN_PASSWORD=""
php artisan migrate --force
if [ "$FIRST_INSTALL" -eq 1 ]; then
    ADMIN_PASSWORD="$(openssl rand -base64 18 | tr -dc 'A-Za-z0-9' | head -c 14)Aa1"
    ADMIN_EMAIL="$ADMIN_EMAIL" ADMIN_PASSWORD="$ADMIN_PASSWORD" php artisan db:seed --force
fi
php artisan config:cache && php artisan route:cache && php artisan view:cache

chown -R www-data:www-data "$APP_DIR"
chmod -R ug+rwX storage bootstrap/cache
chmod 640 .env

echo "==> Konfigurasi Nginx"
cat > /etc/nginx/sites-available/ewallet <<NGINX
server {
    listen 80;
    server_name ${DOMAIN};
    root ${APP_DIR}/public;
    index index.php;
    client_max_body_size 8M;
    server_tokens off;

    location / { try_files \$uri \$uri/ /index.php?\$query_string; }
    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php${PHP}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
    }
    location ~ /\.(?!well-known) { deny all; }
}
NGINX
ln -sf /etc/nginx/sites-available/ewallet /etc/nginx/sites-enabled/ewallet
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx
systemctl enable --now php${PHP}-fpm nginx postgresql

echo "==> Firewall"
ufw allow OpenSSH >/dev/null; ufw allow 'Nginx Full' >/dev/null; ufw --force enable >/dev/null

if [[ ! "$DOMAIN" =~ ^[0-9.]+$ ]]; then
    echo "==> Sertifikat HTTPS (Let's Encrypt)"
    apt-get install -y certbot python3-certbot-nginx
    certbot --nginx -d "$DOMAIN" -m "$ADMIN_EMAIL" --agree-tos --non-interactive --redirect \
        || echo "!! Certbot gagal. Pastikan DNS ${DOMAIN} sudah mengarah ke IP VPS ini, lalu jalankan: certbot --nginx -d ${DOMAIN}"
fi

echo
echo "================ SELESAI ================"
echo "Aplikasi : $(grep ^APP_URL .env | cut -d= -f2)"
if [ -n "$ADMIN_PASSWORD" ]; then
    echo "Admin    : ${ADMIN_EMAIL}"
    echo "Password : ${ADMIN_PASSWORD}   <-- simpan sekarang, tidak ditampilkan lagi"
fi
echo "Webhook Xendit: $(grep ^APP_URL .env | cut -d= -f2)/webhooks/xendit/invoice"
echo "Isi XENDIT_SECRET_KEY & XENDIT_CALLBACK_TOKEN di ${APP_DIR}/.env lalu: php artisan config:cache"
