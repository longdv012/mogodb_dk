FROM php:8.4-cli-alpine

# Install system dependencies
RUN apk add --no-cache \
    libzip-dev \
    zip \
    unzip \
    autoconf \
    g++ \
    make \
    openssl-dev \
    linux-headers

# Install PHP extensions
RUN docker-php-ext-install pcntl zip

# Install MongoDB extension
RUN pecl install mongodb && docker-php-ext-enable mongodb

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy application
COPY . .

# Install dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-req=ext-mongodb 2>/dev/null || true

# Set permissions
RUN chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
