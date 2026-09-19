FROM docker.io/dunglas/frankenphp:1.11-php8.4-trixie AS base

WORKDIR /app

# add additional extensions here:
RUN install-php-extensions \
    @composer \
    apcu \
    pdo_pgsql \
    redis \
    intl \
    zip \
    opcache

RUN apt update && apt install -y --no-install-recommends \
    procps

# --- Dev: todo el código llega por bind mount (compose.yaml), sin COPY ni
# vendor/ propios en la imagen — se instala una vez con `composer install`
# dentro del contenedor y queda en el host. Sin worker mode (Caddyfile por
# defecto de la imagen base, no el custom de prod): cada request relee el
# código, sin reiniciar nada al cambiar una clase. Corre como usuario no-root
# con el UID/GID del host (para que los archivos que el contenedor escriba —
# var/cache, vendor/ — no queden root:root en el bind mount), igual que
# discovery-room.
FROM base AS dev

ARG USER_ID=1000
ARG GROUP_ID=1000

RUN apt-get update && apt-get install --no-install-recommends -y libcap2-bin git unzip \
    && rm -rf /var/lib/apt/lists/* \
    && groupadd --gid ${GROUP_ID} appuser \
    && useradd --uid ${USER_ID} --gid appuser --shell /bin/bash --create-home appuser \
    && setcap 'cap_net_bind_service=+ep' /usr/local/bin/frankenphp \
    && mkdir -p /data/caddy /config/caddy \
    && chown -R appuser:appuser /data /config /app

USER appuser

ENV SERVER_NAME=:80

# Stage para instalar dependencias de PHP y obtener vendor (solo prod — dev
# instala vendor/ en caliente dentro del contenedor, ver arriba)
FROM base AS composer-deps

COPY ./composer.json /app/composer.json
COPY ./composer.lock /app/composer.lock
COPY ./symfony.lock /app/symfony.lock

RUN set -eux; \
	composer install --no-cache --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress

FROM base AS prod

VOLUME /app/var/

# Copy vendor from composer-deps stage
COPY --from=composer-deps /app/vendor /app/vendor

COPY . /app

# Copiar configuración de Caddy — la ruta que realmente lee FrankenPHP en
# tiempo de ejecución es /etc/frankenphp/Caddyfile (así invoca el entrypoint
# de la imagen base: "frankenphp run --config /etc/frankenphp/Caddyfile"),
# no /etc/caddy/Caddyfile — copiar ahí dejaba corriendo silenciosamente el
# Caddyfile por defecto de la imagen base en vez del nuestro.
COPY docker/franken-php/Caddyfile /etc/frankenphp/Caddyfile

ENV COMPOSER_ALLOW_SUPERUSER=1
ENV SERVER_NAME=:80
ENV APP_DEBUG=0
ENV APP_ENV=prod

EXPOSE 80 9090

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY ./docker/franken-php/conf.d/ "$PHP_INI_DIR/conf.d/"

RUN set -eux; \
	mkdir -p var/cache var/log; \
	composer dump-autoload --classmap-authoritative --no-dev; \
	composer dump-env prod; \
	chmod +x bin/console; sync;

RUN php /app/bin/console cache:clear
RUN php /app/bin/console cache:warmup

# set preload opcache config/preload.php
RUN set -eux; \
    if [ -f /app/config/preload.php ]; then \
        echo "opcache.preload=/app/config/preload.php" >> "$PHP_INI_DIR/conf.d/docker-php-ext-opcache.ini"; \
        echo "opcache.preload_user=www-data" >> "$PHP_INI_DIR/conf.d/docker-php-ext-opcache.ini"; \
    else \
        echo "opcache.preload is not set, skipping"; \
    fi
