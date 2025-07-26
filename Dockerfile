
# Use PHP 8.4 with FPM
FROM php:8.4-fpm

# Set working directory
WORKDIR /var/www

# Install system dependencies, PHP extensions, and Composer in one layer
RUN apt-get update && apt-get install -y \
    git curl libpng-dev libonig-dev libxml2-dev libpq-dev zip unzip nodejs npm \
    libicu-dev libzip-dev \
    && docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-configure intl \
    && docker-php-ext-install pdo pdo_pgsql pgsql mbstring exif pcntl bcmath gd intl zip \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Copy application files and set permissions
COPY --chown=www-data:www-data . /var/www

# Fix git ownership issue and install dependencies
RUN git config --global --add safe.directory /var/www \
    && composer install --no-dev --optimize-autoloader \
    && npm install && npm run build && rm -rf node_modules \
    && php artisan storage:link \
    && php artisan filament:cache-components \
    && php artisan icons:cache \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache \
    && chown -R www-data:www-data /var/www \
    && chmod -R 755 /var/www/storage /var/www/bootstrap/cache

# Expose port 9000 (PHP-FPM default port)
EXPOSE 9000

# Start PHP-FPM
CMD ["php-fpm"]
