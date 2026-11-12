#!/bin/sh
set -eu

PORT="${PORT:-10000}"
sed -ri "s/^Listen [0-9]+$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s#<VirtualHost \*:[0-9]+>#<VirtualHost *:${PORT}>#" /etc/apache2/sites-available/000-default.conf

mkdir -p /var/www/html/public/uploads/events /var/www/html/storage/logs
chown -R www-data:www-data /var/www/html/public/uploads /var/www/html/storage

# Free Render services have no interactive shell. Run the idempotent setup at
# startup so a new production database receives its schema and demo data.
php /var/www/html/bin/migrate.php

exec apache2-foreground
