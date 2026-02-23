FROM php:8.4-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    bash \
    curl \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    linux-headers \
    supervisor \
    $PHPIZE_DEPS

# Install PHP extensions
RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    zip \
    intl \
    pcntl \
    opcache \
    bcmath

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# PHP configuration
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

# Set working directory
WORKDIR /var/www

# Copy application
COPY . /var/www

# Set permissions
RUN mkdir -p /var/log/supervisor \
    && mkdir -p /var/www/storage/logs \
    && mkdir -p /var/www/bootstrap/cache \
    && chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache /var/log/supervisor

EXPOSE 9000
CMD ["php-fpm"]
