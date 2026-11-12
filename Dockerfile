FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev libonig-dev libzip-dev unzip \
    && docker-php-ext-install curl mbstring pdo_mysql zip \
    && a2enmod headers rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

COPY . ./

RUN sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf \
    && printf '%s\n' \
        '<Directory /var/www/html/public>' \
        '    AllowOverride All' \
        '    Require all granted' \
        '</Directory>' > /etc/apache2/conf-available/nevents.conf \
    && a2enconf nevents \
    && mkdir -p storage/logs public/uploads/events \
    && chown -R www-data:www-data storage public/uploads

COPY docker/start.sh /usr/local/bin/nevents-start
RUN chmod +x /usr/local/bin/nevents-start

EXPOSE 10000

CMD ["/usr/local/bin/nevents-start"]
