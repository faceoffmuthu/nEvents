FROM php:8.2-cli

# Install dependencies and PHP extensions
RUN apt-get update && apt-get install -y git unzip libzip-dev default-mysql-client \
    && docker-php-ext-install pdo_mysql zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy the application files
COPY . .

# Install PHP packages
RUN composer install --no-dev --optimize-autoloader

# Expose the port Render assigns
EXPOSE $PORT

# Start the PHP built-in server
CMD php -S 0.0.0.0:$PORT -t public router.php
