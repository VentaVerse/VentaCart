# syntax=docker/dockerfile:1.7

# =============================================================================
# Stage 1: PHP dependencies
# =============================================================================
FROM composer:2 AS composer-deps

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --ignore-platform-reqs \
        --no-interaction

# =============================================================================
# Stage 2: Frontend asset build
# =============================================================================
FROM node:20-alpine AS node-build

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund --prefer-offline

COPY resources/ ./resources/
COPY public/ ./public/
COPY vite.config.js ./

RUN npm run build

# =============================================================================
# Stage 3: Runtime
# =============================================================================
FROM php:8.4-fpm-alpine AS runtime

# Build-time dependencies for PHP extensions, then we strip them out
RUN apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        autoconf \
        freetype-dev \
        icu-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        libwebp-dev \
        libzip-dev \
        oniguruma-dev \
    && apk add --no-cache \
        bash \
        curl \
        freetype \
        icu \
        libjpeg-turbo \
        libpng \
        libwebp \
        libzip \
        mariadb-client \
        nginx \
        oniguruma \
        supervisor \
        tzdata \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        gd \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_mysql \
        zip \
    && apk del .build-deps \
    && rm -rf /var/cache/apk/*

# Pull composer binary in (used at runtime for `composer dump-autoload`)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Application source
COPY . .

# Vendor + built assets from previous stages
COPY --from=composer-deps /app/vendor ./vendor
COPY --from=node-build /app/public/build ./public/build

# Regenerate optimized autoloader now that source + vendor coexist
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

# Container-specific configs
COPY docker/nginx.conf            /etc/nginx/nginx.conf
COPY docker/php-fpm.conf          /usr/local/etc/php-fpm.d/zzz-venta.conf
COPY docker/php.ini               /usr/local/etc/php/conf.d/zzz-venta.ini
COPY docker/opcache.ini           /usr/local/etc/php/conf.d/zzz-opcache.ini
COPY docker/supervisord.conf      /etc/supervisord.conf
COPY docker/crontab               /etc/crontabs/www-data
COPY docker/entrypoint.sh         /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh \
    && mkdir -p /var/log/supervisor /run/nginx \
    && chown -R www-data:www-data \
        /var/www/html/storage \
        /var/www/html/bootstrap/cache \
        /var/log/supervisor \
        /run/nginx \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Persistent volumes — tenant-specific data lives here, survives container restarts
VOLUME ["/var/www/html/storage/app", "/var/www/html/storage/logs"]

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl -fsS http://127.0.0.1/up || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
