#!/bin/sh
set -eu

PORT="${PORT:-10000}"
sed -ri "s/^Listen [0-9]+$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s#<VirtualHost \*:[0-9]+>#<VirtualHost *:${PORT}>#" /etc/apache2/sites-available/000-default.conf

mkdir -p /var/www/html/public/uploads/events /var/www/html/storage/logs
chown -R www-data:www-data /var/www/html/public/uploads /var/www/html/storage

# A normal web deployment must not be held hostage by a temporarily unavailable
# database. Set RUN_MIGRATIONS=1 in Render only when a schema/data migration is
# actually required; the app itself still uses its configured database normally.
if [ "${RUN_MIGRATIONS:-0}" = "1" ]; then
    php /var/www/html/bin/migrate.php
fi

exec apache2-foreground
