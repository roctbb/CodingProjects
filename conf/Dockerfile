# syntax=docker/dockerfile:1

# Builder stage: install PHP deps with Composer
FROM php:8.4-cli AS builder

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libzip-dev \
    libxml2-dev \
    libpq-dev \
    zip \
    && docker-php-ext-install -j"$(nproc)" zip pdo pdo_pgsql xml \
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
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev --no-interaction

# Final production image: PHP-FPM
FROM php:8.4-fpm AS app

RUN apt-get update && apt-get install -y --no-install-recommends \
    libzip-dev \
    libxml2-dev \
    libpq-dev \
    zip \
    && docker-php-ext-install -j"$(nproc)" zip pdo pdo_pgsql xml \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

# Copy app and vendor from builder
COPY --from=builder /app /var/www/html

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache || true

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stack

EXPOSE 9000
CMD ["php-fpm"]
