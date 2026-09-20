# Plantilla de build para lang=php, framework=symfony (FrankenPHP, modo
# classic: cada request arranca el kernel, como con PHP-FPM).
#
# Convención sobre configuración (ver docs/examples/podium-example.yaml):
# composer.json en la raíz de `src`, front controller en public/index.php.
# Sin comando configurable.
#
# No hace falta Caddyfile propio: el de la imagen base ya sirve /app/public
# con php_server, que es exactamente el layout de Symfony.

FROM docker.io/dunglas/frankenphp:1.11-php8.4-trixie
WORKDIR /app

RUN install-php-extensions \
    @composer \
    apcu \
    pdo_pgsql \
    redis \
    intl \
    zip \
    opcache

ENV COMPOSER_ALLOW_SUPERUSER=1
ENV SERVER_NAME=:80
ENV APP_ENV=prod
ENV APP_DEBUG=0

COPY . .
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress --prefer-dist
RUN php bin/console cache:warmup

EXPOSE 80
