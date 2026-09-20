# Plantilla de build para lang=php, framework=laravel (FrankenPHP, modo
# classic: cada request arranca la aplicación, como con PHP-FPM).
#
# Convención sobre configuración (ver docs/examples/podium-example.yaml):
# composer.json en la raíz de `src`, front controller en public/index.php.
# Sin comando configurable.
#
# APP_KEY: Laravel no arranca sin ella. Si el repo no trae .env se genera una
# en el build, para que la demo levante igual; un equipo que necesite una
# clave estable la declara en su propio .env.

FROM docker.io/dunglas/frankenphp:1.11-php8.4-trixie
WORKDIR /app

RUN install-php-extensions \
    @composer \
    apcu \
    pdo_pgsql \
    redis \
    intl \
    zip \
    opcache \
    pcntl

ENV COMPOSER_ALLOW_SUPERUSER=1
ENV SERVER_NAME=:80
ENV APP_ENV=production
ENV APP_DEBUG=false

COPY . .
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress --prefer-dist
RUN if [ ! -f .env ]; then cp .env.example .env; fi && php artisan key:generate --force
RUN chmod -R ug+w storage bootstrap/cache

EXPOSE 80
