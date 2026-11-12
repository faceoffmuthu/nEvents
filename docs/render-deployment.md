# Deploying N Events on Render

N Events needs a private MySQL 8 service and a public Docker web service in the same Render region. Never commit `.env`, database passwords, SMTP credentials, or API tokens.

Create a Render private MySQL service named `nevents-mysql` with `MYSQL_DATABASE=nevents`, a non-root `MYSQL_USER`, separate strong user and root passwords, and a persistent disk mounted at `/var/lib/mysql`.

Create the web service from this repository with Docker, health-check path `/login`, and a persistent disk mounted at `/var/www/html/public/uploads` for event poster uploads. Set `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE=true`, and `APP_URL` to the final HTTPS Render/custom-domain URL.

Set `DB_HOST=nevents-mysql`, `DB_PORT=3306`, and the matching database credentials as Render environment variables. After the first successful deploy, use the Render shell to run `php bin/migrate.php` and create an admin only for a new production database.
