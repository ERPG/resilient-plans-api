FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libxml2-dev libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql zip xml opcache \
    && rm -rf /var/lib/apt/lists/*

# OPcache: pre-compila PHP en memoria, evita parsear archivos en cada request.
# validate_timestamps=0 asume que el código no cambia en runtime (prod assumption).
RUN { \
    echo "opcache.enable=1"; \
    echo "opcache.memory_consumption=256"; \
    echo "opcache.max_accelerated_files=20000"; \
    echo "opcache.validate_timestamps=0"; \
    echo "opcache.interned_strings_buffer=16"; \
} > /usr/local/etc/php/conf.d/opcache.ini

# FPM: static pool evita el overhead de spawn dinámico bajo carga.
# 20 workers = límite razonable para un nodo Docker en dev/staging.
RUN { \
    echo "[www]"; \
    echo "pm = static"; \
    echo "pm.max_children = 20"; \
} > /usr/local/etc/php-fpm.d/zz-perf.conf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
