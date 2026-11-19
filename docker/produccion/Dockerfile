# Imagen de producción (plan 1.12b; docs/operaciones/requisitos-servidor.md). Se construye desde la raíz del repositorio:
#   docker compose -f docker/produccion/compose.yml build
# Con la misma imagen corren la aplicación (PHP-FPM), las dos colas y el programador; `web` es nginx con lo público.
# Versiones fijas: subirlas es cambiar este archivo y reconstruir.

# --- Base: PHP con sus extensiones y el cliente de PostgreSQL. ---
FROM php:8.5.12-fpm-bookworm AS base

# PHP2Shell (CVE-2026-109618 y CVE-2026-109619): nunca menos de 8.5.12. Si alguien baja la etiqueta, la construcción falla.
RUN php -r 'exit(version_compare(PHP_VERSION, "8.5.12", ">=") ? 0 : 1);' \
    || (echo "PHP ${PHP_VERSION}: se necesita 8.5.12 o posterior (PHP2Shell)." && exit 1)

# `pg_dump` 16 del repositorio oficial de PostgreSQL: el de Debian bookworm es el 15 y no respalda un servidor 16.
RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates curl gnupg \
    && install -d /usr/share/postgresql-common/pgdg \
    && curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc -o /usr/share/postgresql-common/pgdg/apt.postgresql.org.asc \
    && echo "deb [signed-by=/usr/share/postgresql-common/pgdg/apt.postgresql.org.asc] https://apt.postgresql.org/pub/repos/apt bookworm-pgdg main" > /etc/apt/sources.list.d/pgdg.list \
    && apt-get update \
    && apt-get upgrade -y \
    && apt-get install -y --no-install-recommends postgresql-client-16 libpq-dev libicu-dev libzip-dev libgmp-dev \
        libpng-dev libjpeg62-turbo-dev libwebp-dev \
    # GD (plan 2.22c): dompdf la necesita para dibujar el logo de Techquk (PNG, JPG o WEBP) en los PDF.
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql intl bcmath zip pcntl gmp gd \
    # Al final solo las bibliotecas, sin sus cabeceras ni las herramientas de la construcción.
    && apt-mark manual libpq5 libicu72 libzip4 libgmp10 libpng16-16 libjpeg62-turbo libwebp7 \
    && apt-get purge -y gnupg curl libpq-dev libicu-dev libzip-dev libgmp-dev libpng-dev libjpeg62-turbo-dev libwebp-dev \
    && apt-get autoremove -y \
    && rm -rf /var/lib/apt/lists/*

RUN pg_dump --version | grep -q ' 16\.' || (echo "pg_dump no es la versión 16." && exit 1)
RUN php -r 'exit(function_exists("imagecreatefromwebp") && function_exists("imagecreatefromjpeg") ? 0 : 1);' \
    || (echo "GD sin JPG o WEBP: el logo no saldría en los PDF." && exit 1)

COPY docker/produccion/php.ini /usr/local/etc/php/conf.d/zz-techquk.ini
COPY docker/produccion/fpm.conf /usr/local/etc/php-fpm.d/zz-techquk.conf

WORKDIR /var/www/html

# --- Dependencias: sin las de desarrollo; `filament:upgrade` publica los archivos de Filament en public/. ---
FROM base AS dependencias

COPY --from=composer:2.10.3 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN apt-get update && apt-get install -y --no-install-recommends unzip && rm -rf /var/lib/apt/lists/* \
    && composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && composer check-platform-reqs --no-dev \
    && php artisan package:discover --ansi \
    && php artisan filament:assets --ansi \
    && rm -rf storage/logs/* storage/framework/cache/data/* storage/framework/sessions/* storage/framework/views/* storage/framework/testing \
    && mkdir -p bootstrap/cache/views

# --- Aplicación: PHP-FPM, las colas y el programador (el compose elige el comando). ---
FROM base AS app

# El código es de root: quien corre PHP (www-data) no puede reescribirlo. Solo escribe en storage (el volumen) y en
# bootstrap/cache (las cachés y vistas compiladas de cada contenedor).
COPY --from=dependencias /var/www/html /var/www/html
RUN chown -R www-data:www-data storage bootstrap/cache
COPY --chmod=755 docker/produccion/arrancar.sh /usr/local/bin/arrancar

USER www-data
ENTRYPOINT ["arrancar"]
CMD ["php-fpm"]

# --- Web: nginx con solo lo público (nunca el código ni storage). ---
FROM nginx:1.28.3-alpine AS web

COPY docker/produccion/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=dependencias /var/www/html/public /var/www/html/public
