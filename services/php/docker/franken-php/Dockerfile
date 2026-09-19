FROM docker.io/dunglas/frankenphp:1.11-php8.4-trixie AS base

WORKDIR /app

VOLUME /app/var/

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

# Stage para instalar dependencias de PHP y obtener vendor
FROM base AS composer-deps

COPY ./composer.json /app/composer.json
COPY ./composer.lock /app/composer.lock
COPY ./symfony.lock /app/symfony.lock

RUN set -eux; \
	composer install --no-cache --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress

FROM base AS prod

# Copy vendor from composer-deps stage
COPY --from=composer-deps /app/vendor /app/vendor

COPY . /app

# Copiar configuración de Caddy
COPY docker/franken-php/Caddyfile /etc/caddy/Caddyfile

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
