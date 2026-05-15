# syntax=docker/dockerfile:1

# Base image: PHP 8.3 CLI with the extensions the app and workers need.
FROM php:8.3-cli-alpine AS base

RUN set -eux; \
    apk add --no-cache postgresql-dev; \
    apk add --no-cache --virtual .build-deps $PHPIZE_DEPS linux-headers; \
    docker-php-ext-install pdo pdo_pgsql opcache; \
    pecl install redis; \
    docker-php-ext-enable redis; \
    apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Application image. Dependencies are installed in their own layer so that
# source-only changes do not invalidate the Composer cache.
FROM base AS app

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-interaction --no-scripts --prefer-dist --no-autoloader

COPY . .
RUN set -eux; \
    composer dump-autoload --optimize; \
    mkdir -p var; \
    chown -R www-data:www-data var

EXPOSE 8000

# The built-in PHP server keeps the sample simple. Production would use
# PHP-FPM behind Nginx; see ARCHITECTURE.md.
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
