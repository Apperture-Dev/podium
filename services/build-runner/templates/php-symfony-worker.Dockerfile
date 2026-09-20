# Plantilla de build para lang=php, framework=symfony-worker (FrankenPHP en
# worker mode: el kernel se arranca una vez y se reutiliza entre requests).
#
# Misma convención que php/symfony, más una diferencia: la plantilla instala
# `runtime/frankenphp-symfony` y fija `APP_RUNTIME`. Sin ese runtime, el
# `worker` de FrankenPHP ejecuta public/index.php entero en cada request y no
# se gana nada — el bucle de peticiones lo aporta el runtime, no el servidor.
#
# La configuración del worker va por FRANKENPHP_CONFIG en vez de un Caddyfile
# propio: el entrypoint de la imagen base ya la inyecta en su Caddyfile.
#
# Cuidado con el estado entre peticiones: en worker mode los servicios siguen
# vivos de un request al siguiente. Un servicio que guarde estado del usuario
# actual lo filtra al siguiente visitante.

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
RUN composer require runtime/frankenphp-symfony --no-interaction --no-progress --update-no-dev

# Después de instalar el runtime, nunca antes: `vendor/autoload_runtime.php`
# resuelve APP_RUNTIME en cada script de Composer, así que declararlo arriba
# revienta el propio `composer install` que lo instala ("Class
# Runtime\FrankenPhpSymfony\Runtime not found", cache:clear con código 255).
ENV APP_RUNTIME="Runtime\\FrankenPhpSymfony\\Runtime"
ENV FRANKENPHP_CONFIG="worker ./public/index.php"

RUN php bin/console cache:warmup

EXPOSE 80
