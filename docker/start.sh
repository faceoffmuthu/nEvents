#!/bin/sh
set -eu

PORT="${PORT:-10000}"
sed -ri "s/^Listen [0-9]+$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s#<VirtualHost \*:[0-9]+>#<VirtualHost *:${PORT}>#" /etc/apache2/sites-available/000-default.conf

mkdir -p /var/www/html/public/uploads/events /var/www/html/storage/logs
chown -R www-data:www-data /var/www/html/public/uploads /var/www/html/storage

# Free Render services have no interactive shell. The database can also take a
# short time to accept connections after a deploy or restart, so retry the
# idempotent setup before Apache begins serving traffic.
attempt=1
max_attempts=24
until php /var/www/html/bin/migrate.php; do
    if [ "$attempt" -ge "$max_attempts" ]; then
        echo "Database setup failed after ${max_attempts} attempts."
        exit 1
    fi
    echo "Database is not ready; retrying in 5 seconds (${attempt}/${max_attempts})..."
    attempt=$((attempt + 1))
    sleep 5
done

exec apache2-foreground
