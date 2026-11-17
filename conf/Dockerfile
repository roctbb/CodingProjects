# syntax=docker/dockerfile:1

# Frontend assets stage: build Vite output into public/build
FROM node:22-alpine AS assets

WORKDIR /app

COPY package*.json ./
RUN npm install --no-audit --no-fund

COPY . .
RUN npm run build

# Compile extensions once. Build tools stay outside the production image.
# Keep both PHP and the runtime on the same Debian release for ABI compatibility.
FROM php:8.4.27-fpm-trixie AS php-extensions

RUN apt-get update && apt-get install -y --no-install-recommends \
    libcurl4-openssl-dev \
    libzip-dev \
    libxml2-dev \
    libonig-dev \
    libpq-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libwebp-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" curl zip pdo pdo_pgsql xml gd mbstring pcntl \
    && curl -fSL --retry 3 --retry-delay 3 --connect-timeout 15 --max-time 120 \
        https://pecl.php.net/get/redis-6.3.0.tgz -o /tmp/redis.tgz \
    && pecl install /tmp/redis.tgz \
    && rm /tmp/redis.tgz \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

# Include dependencies of PHP, FPM and every bundled/compiled extension.
# Resolve Debian package names rather than hardcoding versioned library names.
RUN set -eu; \
    ldd /usr/local/bin/php /usr/local/sbin/php-fpm /usr/local/lib/php/extensions/*/*.so > /tmp/php-runtime-ldd; \
    ! grep -q 'not found' /tmp/php-runtime-ldd; \
    awk '/=>/ { so = $(NF-1); if (index(so, "/usr/local/") == 1) next; sub("^/(usr/)?", "", so); print "*" so }' /tmp/php-runtime-ldd \
    | sort -u > /tmp/php-runtime-paths; \
    dpkg-query --search $(cat /tmp/php-runtime-paths) > /tmp/php-runtime-owners; \
    awk -F: '/^[^[:space:]]+: / { print $1 }' /tmp/php-runtime-owners \
    | sort -u > /tmp/php-runtime-packages \
    && test -s /tmp/php-runtime-packages

# Composer and its download tools are only needed while assembling the app.
FROM php-extensions AS builder

RUN apt-get update && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /app

# Install deps first for better layer caching
COPY composer.json composer.lock ./
RUN mkdir -p database
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --optimize-autoloader \
    --classmap-authoritative

# Copy application source after vendor is cached
COPY . .

# Refresh optimized autoload for full source tree
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
    && composer dump-autoload --optimize --classmap-authoritative --no-dev --no-interaction --no-scripts

# Assemble assets before copying the app so each release has one asset layer.
COPY --from=assets /app/public/build /app/public/build

# Start fresh: the upstream PHP image also contains compilers in its base layers.
FROM debian:trixie-slim AS app

COPY --from=php-extensions /tmp/php-runtime-packages /tmp/php-runtime-packages
RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates tzdata $(cat /tmp/php-runtime-packages) \
    && rm /tmp/php-runtime-packages \
    && rm -rf /var/lib/apt/lists/*

COPY --from=php-extensions /usr/local/bin/php /usr/local/bin/docker-php-entrypoint /usr/local/bin/
COPY --from=php-extensions /usr/local/sbin/php-fpm /usr/local/sbin/php-fpm
COPY --from=php-extensions /usr/local/lib/php/extensions /usr/local/lib/php/extensions
COPY --from=php-extensions /usr/local/etc /usr/local/etc

RUN { \
        echo 'max_execution_time=180'; \
        echo 'max_input_time=180'; \
        echo 'default_socket_timeout=180'; \
        echo 'memory_limit=256M'; \
        echo 'upload_max_filesize=50M'; \
        echo 'post_max_size=55M'; \
    } > /usr/local/etc/php/conf.d/codingprojects-timeouts.ini

WORKDIR /var/www/html

# Copy app and vendor from builder
COPY --from=builder /app /var/www/html

RUN mkdir -p /var/www/html/storage/app-private/room-character-sources \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

ENV PHP_INI_DIR=/usr/local/etc/php \
    APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stack

EXPOSE 9000
ENTRYPOINT ["docker-php-entrypoint"]
STOPSIGNAL SIGQUIT
CMD ["php-fpm"]

# Web stage: nginx with built static assets from the app image
FROM nginx:1.27-alpine AS web

COPY conf/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public
