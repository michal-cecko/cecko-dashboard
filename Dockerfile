# ---- Build stage ----
FROM synapps-dashboard-base:build AS build

WORKDIR /var/www

COPY package*.json ./
RUN npm ci --no-audit

COPY composer.json composer.lock ./
RUN composer install --optimize-autoloader --no-dev --no-scripts --no-interaction

COPY . /var/www

RUN git config --global --add safe.directory /var/www \
    && composer run post-autoload-dump \
    && npm run build \
    && php artisan storage:link || true \
    && vendor/bin/rr get-binary \
    && ls -la /var/www/rr || echo "rr not in /var/www" \
    && which rr || echo "rr not in PATH"

# ---- Production stage ----
FROM synapps-dashboard-base:runtime

WORKDIR /var/www

COPY --from=build --chown=www-data:www-data /var/www /var/www

RUN chmod -R 755 /var/www/storage /var/www/bootstrap/cache /var/www/public \
    && chmod +x /var/www/rr \
    && /var/www/rr --version

EXPOSE 8000

HEALTHCHECK --interval=30s --timeout=10s --start-period=30s --retries=3 \
    CMD curl -f http://localhost:8000/up || exit 1

CMD ["bash", "-c", "php artisan optimize && php artisan octane:start --server=roadrunner --host=0.0.0.0 --port=8000 --workers=auto --max-requests=500"]
