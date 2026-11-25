FROM composer:2 AS composer-bin

# The lockfile contains packages that require PHP >= 8.4.1. Run Composer on the
# same PHP major version as the runtime instead of relying on composer:2's base.
FROM php:8.4-cli-alpine AS vendor
COPY --from=composer-bin /usr/bin/composer /usr/bin/composer

# Composer's --prefer-dist installs need a real unzip, not just BusyBox.
RUN apk add --no-cache unzip

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
        gettext \
        nginx \
        supervisor \
        icu-dev \
        libxml2-dev \
        libzip-dev \
        oniguruma-dev \
        libpq-dev \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        mbstring \
        opcache \
        pdo_pgsql \
        xml \
        zip

WORKDIR /var/www/html

COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY docker/nginx.conf.template /etc/nginx/http.d/default.conf.template
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh \
    && mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

ENV PORT=10000 \
    APP_ENV=production \
    DB_CONNECTION=pgsql \
    DB_SSLMODE=require \
    LOG_CHANNEL=stderr

EXPOSE 10000

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s \
    CMD wget -q --spider "http://127.0.0.1:${PORT}/up" || exit 1

CMD ["/usr/local/bin/entrypoint.sh"]
