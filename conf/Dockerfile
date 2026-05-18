# syntax=docker/dockerfile:1

# Frontend assets stage: build Vite output into public/build
FROM node:22-alpine AS assets

WORKDIR /app

COPY package*.json ./
RUN npm install --no-audit --no-fund

COPY . .
RUN npm run build

# Builder stage: install PHP deps with Composer
FROM php:8.4-cli AS builder

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libcurl4-openssl-dev \
    libzip-dev \
    libxml2-dev \
    libpq-dev \
    libpng-dev \
    zip \
    && docker-php-ext-install -j"$(nproc)" curl zip pdo pdo_pgsql xml gd \
    && pecl install redis \
    && docker-php-ext-enable redis \
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

# Final production image: PHP-FPM
FROM php:8.4-fpm AS app

RUN apt-get update && apt-get install -y --no-install-recommends \
    libcurl4-openssl-dev \
    libzip-dev \
    libxml2-dev \
    libpq-dev \
    zip \
    && docker-php-ext-install -j"$(nproc)" curl zip pdo pdo_pgsql xml \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

RUN { \
        echo 'max_execution_time=180'; \
        echo 'max_input_time=180'; \
        echo 'default_socket_timeout=180'; \
        echo 'upload_max_filesize=10M'; \
        echo 'post_max_size=12M'; \
    } > /usr/local/etc/php/conf.d/codingprojects-timeouts.ini

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

# Copy app and vendor from builder
COPY --from=builder /app /var/www/html
COPY --from=assets /app/public/build /var/www/html/public/build

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache || true

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stack

EXPOSE 9000
CMD ["php-fpm"]

# Web stage: nginx with built static assets from the app image
FROM nginx:1.27-alpine AS web

COPY conf/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public
