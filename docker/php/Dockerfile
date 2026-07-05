FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libxml2-dev libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql zip xml \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
