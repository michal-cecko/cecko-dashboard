# ---- Base stage: PHP 8.4 + extensions + composer + node (self-contained, was Dockerfile.base) ----
FROM php:8.4-cli-alpine AS base

WORKDIR /var/www

RUN apk add --no-cache \
    bash git curl nodejs npm \
    libpng-dev oniguruma-dev libxml2-dev postgresql-dev \
    icu-dev libzip-dev sqlite-dev xz linux-headers \
    autoconf gcc g++ make \
    && pecl install redis && docker-php-ext-enable redis \
    && docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-configure intl \
    && docker-php-ext-install pdo pdo_pgsql pdo_sqlite pgsql mbstring exif pcntl bcmath gd intl zip opcache sockets \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# ---- Build stage ----
FROM base AS build

WORKDIR /var/www

COPY package*.json ./
RUN npm ci --no-audit

ARG COMPOSER_AUTH
COPY composer.json composer.lock ./
RUN COMPOSER_AUTH="$COMPOSER_AUTH" composer install --optimize-autoloader --no-dev --no-scripts --no-interaction

COPY . /var/www

RUN git config --global --add safe.directory /var/www \
    && composer run post-autoload-dump \
    && npm run build \
    && php artisan storage:link || true \
    && vendor/bin/rr get-binary \
    && ls -la /var/www/rr || echo "rr not in /var/www" \
    && which rr || echo "rr not in PATH"

# ---- Production stage (lean runtime) ----
FROM php:8.4-cli-alpine

WORKDIR /var/www

RUN echo "cd /var/www" >> /etc/profile \
    && echo "cd /var/www" >> /root/.bashrc

RUN apk add --no-cache \
    bash curl libpng oniguruma libxml2 libpq \
    icu-libs libzip xz

# Copy compiled PHP extensions from build stage
COPY --from=build /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=build /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

# OPcache
RUN echo "[opcache]" > /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=256" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.interned_strings_buffer=64" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.max_accelerated_files=32531" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.save_comments=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.jit=1255" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.jit_buffer_size=128M" >> /usr/local/etc/php/conf.d/opcache.ini

# PHP settings
RUN echo "upload_max_filesize = 128M" > /usr/local/etc/php/conf.d/php.ini \
    && echo "post_max_size = 128M" >> /usr/local/etc/php/conf.d/php.ini \
    && echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/php.ini \
    && echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/php.ini \
    && echo "realpath_cache_size = 4096K" >> /usr/local/etc/php/conf.d/php.ini \
    && echo "realpath_cache_ttl = 600" >> /usr/local/etc/php/conf.d/php.ini

# Copy application from build stage
COPY --from=build --chown=www-data:www-data /var/www /var/www

RUN chmod -R 755 /var/www/storage /var/www/bootstrap/cache /var/www/public \
    && chmod +x /var/www/rr \
    && /var/www/rr --version

EXPOSE 8000

HEALTHCHECK --interval=30s --timeout=10s --start-period=30s --retries=3 \
    CMD curl -f http://localhost:8000/up || exit 1

ENV LOG_CHANNEL=single
ENV ENV=/etc/profile

CMD ["bash", "-c", "php artisan optimize && touch storage/logs/laravel.log && tail -f storage/logs/laravel.log & php artisan octane:start --server=roadrunner --host=0.0.0.0 --port=8000 --workers=2 --max-requests=500"]
