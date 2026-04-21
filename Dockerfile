FROM php:8.4-fpm-alpine AS base

RUN apk add --no-cache \
    sqlite-libs \
    icu-libs \
    freetype \
    libpng \
    libjpeg-turbo \
    ttf-dejavu \
    freetype-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    gd \
    intl \
    opcache \
    pdo_sqlite \
    && apk del --no-cache \
    freetype-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

FROM base AS deps

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --prefer-dist --optimize-autoloader

FROM base AS production

COPY --from=deps /app/vendor /app/vendor
COPY . /app

RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p /app/storage \
    && chown -R www-data:www-data /app/storage

ENV APP_ENV=production
ENV APP_DEBUG=false
ENV WAASEYAA_DB=/app/storage/waaseyaa.sqlite

EXPOSE 9000

USER www-data
