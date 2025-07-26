# Use PHP 8.4 CLI with Alpine
FROM php:8.4-cli-alpine

# Set working directory
WORKDIR /var/www

# Install system dependencies and PHP extensions
RUN apk add --no-cache \
    git curl nodejs npm \
    libpng-dev oniguruma-dev libxml2-dev postgresql-dev \
    icu-dev libzip-dev \
    && docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-configure intl \
    && docker-php-ext-install pdo pdo_pgsql pgsql mbstring exif pcntl bcmath gd intl zip \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copy application files and set permissions
COPY --chown=www-data:www-data . /var/www

# Install dependencies and setup Laravel/Filament
RUN git config --global --add safe.directory /var/www \
    && composer install --optimize-autoloader \
    && php artisan storage:link \
    && chown -R www-data:www-data /var/www \
    && chmod -R 755 /var/www/storage /var/www/bootstrap/cache

# Expose port 8000
EXPOSE 8000

# Clear caches and start server (caching happens AFTER env vars are loaded)
CMD ["sh", "-c", "php artisan config:clear && php artisan cache:clear && php artisan config:cache && php artisan serve --host=0.0.0.0 --port=8000"]
