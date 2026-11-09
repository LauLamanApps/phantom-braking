#!/usr/bin/env bash
# One-time setup of a fresh Debian 12 (bookworm) or 13 (trixie) LXC for phantombraking.eu.
# Run as root inside the container, from a checkout of this repository:
#   bash .deploy/provision.sh
# Safe to re-run: it skips what already exists and never overwrites shared/.env.local.
set -euo pipefail

DIR="$(cd "$(dirname "$0")" && pwd)"
SITE=/var/www/phantombraking.eu
PHP=8.5

echo "==> Packages"
apt-get update
apt-get install -y ca-certificates curl gnupg lsb-release git unzip acl sudo openssl nginx postgresql-common supervisor unattended-upgrades cron

# PHP 8.5 is not in Debian itself: packages.sury.org (Ondřej Surý, the Debian PHP maintainer).
if [ ! -f /etc/apt/sources.list.d/php.list ]; then
    curl -fsSL https://packages.sury.org/php/apt.gpg -o /usr/share/keyrings/deb.sury.org-php.gpg
    echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list
    apt-get update
fi
# PostgreSQL 17 (as in dev) from the PostgreSQL project's own repository; Debian 12 itself ships 15.
if [ ! -f /etc/apt/sources.list.d/pgdg.list ] && [ ! -f /etc/apt/sources.list.d/pgdg.sources ]; then
    /usr/share/postgresql-common/pgdg/apt.postgresql.org.sh -y
fi
apt-get install -y postgresql-17

apt-get install -y php$PHP-fpm php$PHP-cli php$PHP-pgsql php$PHP-intl php$PHP-gd php$PHP-mbstring php$PHP-xml php$PHP-curl php$PHP-zip

if ! command -v composer >/dev/null; then
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
    expected="$(curl -fsSL https://composer.github.io/installer.sig)"
    [ "$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")" = "$expected" ] || { echo "Composer installer checksum mismatch"; exit 1; }
    php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
    rm /tmp/composer-setup.php
fi

echo "==> PHP settings"
for sapi in fpm cli; do install -m 644 "$DIR/php/99-phantom-braking.ini" "/etc/php/$PHP/$sapi/conf.d/99-phantom-braking.ini"; done
systemctl restart php$PHP-fpm

echo "==> Deploy user"
id deployer >/dev/null 2>&1 || adduser --disabled-password --gecos "" deployer
usermod -aG www-data deployer
install -m 440 "$DIR/sudoers/deployer" /etc/sudoers.d/deployer
visudo -cf /etc/sudoers.d/deployer
install -d -o deployer -g deployer -m 700 /home/deployer/.ssh
touch /home/deployer/.ssh/authorized_keys && chown deployer:deployer /home/deployer/.ssh/authorized_keys && chmod 600 /home/deployer/.ssh/authorized_keys
# The clone runs on this server with the forwarded agent; trust GitHub's host key up front.
ssh-keyscan github.com 2>/dev/null >> /home/deployer/.ssh/known_hosts && chown deployer:deployer /home/deployer/.ssh/known_hosts

echo "==> Site directory"
install -d -o deployer -g www-data -m 2775 "$SITE" "$SITE/shared" "$SITE/shared/var" "$SITE/shared/var/log" "$SITE/shared/var/tailwind" "$SITE/shared/var/storage"

echo "==> Database"
if ! sudo -u postgres psql -tAc "SELECT 1 FROM pg_roles WHERE rolname = 'phantom_braking'" | grep -q 1; then
    DB_PASSWORD="$(openssl rand -hex 24)"
    sudo -u postgres psql -c "CREATE ROLE phantom_braking LOGIN PASSWORD '$DB_PASSWORD'"
    sudo -u postgres createdb -O phantom_braking phantom_braking
else
    DB_PASSWORD=""
fi

echo "==> Production settings (shared/.env.local)"
ENV_FILE="$SITE/shared/.env.local"
if [ ! -f "$ENV_FILE" ]; then
    [ -n "$DB_PASSWORD" ] || { echo "Database role exists but .env.local doesn't: set DATABASE_URL by hand"; DB_PASSWORD=SET_ME; }
    sed -e "s/^APP_SECRET=__GENERATED__/APP_SECRET=$(openssl rand -hex 32)/" \
        -e "s/^PLATE_FINGERPRINT_KEY=__GENERATED__/PLATE_FINGERPRINT_KEY=$(openssl rand -hex 32)/" \
        -e "s/__DB_PASSWORD__/$DB_PASSWORD/" \
        "$DIR/env.local.dist" > "$ENV_FILE"
    chown deployer:www-data "$ENV_FILE"
    chmod 640 "$ENV_FILE"
    echo "    Written. Fill in TRUSTED_PROXIES, MEDIA_S3_*, SENTRY_DSN and OPERATOR_* before the first deploy."
else
    echo "    Exists, left untouched."
fi

echo "==> nginx"
install -m 644 "$DIR/nginx/phantombraking.eu.conf" /etc/nginx/sites-available/phantombraking.eu.conf
ln -sf /etc/nginx/sites-available/phantombraking.eu.conf /etc/nginx/sites-enabled/phantombraking.eu.conf
rm -f /etc/nginx/sites-enabled/default
install -m 644 "$DIR/logrotate/nginx-phantom-braking" /etc/logrotate.d/nginx-phantom-braking
nginx -t && systemctl reload nginx

echo "==> Messenger worker"
install -m 644 "$DIR/supervisor/phantom-braking-messenger.conf" /etc/supervisor/conf.d/phantom-braking-messenger.conf
# Started after the first deploy, when current/ exists: supervisorctl reread && supervisorctl update

echo "==> Backups"
install -d -o postgres -g postgres -m 700 /var/backups/phantom-braking
install -m 644 "$DIR/cron/phantom-braking-backup" /etc/cron.d/phantom-braking-backup

echo "==> Security updates"
dpkg-reconfigure -f noninteractive unattended-upgrades

cat <<DONE

Done. Next:
  1. Add your SSH public key to /home/deployer/.ssh/authorized_keys
  2. Edit $ENV_FILE (TRUSTED_PROXIES, MEDIA_S3_*, SENTRY_DSN, OPERATOR_*)
  3. From your machine: make deploy
  4. Here: supervisorctl reread && supervisorctl update
  5. Here: sudo -u www-data php $SITE/current/bin/console app:admin:create "Your Name"
DONE
