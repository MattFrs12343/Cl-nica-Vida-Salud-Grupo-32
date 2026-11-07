FROM composer:2 AS composer

FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev unzip \
    && docker-php-ext-install pdo_pgsql \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/bin/composer

COPY docker/php/vhost.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

# En local, docker-compose monta el proyecto como volumen y estos archivos
# copiados quedan tapados por el bind mount — esto solo importa para builds
# standalone (p. ej. Render), que no tienen ese volumen.
COPY . .
RUN composer install --no-dev --optimize-autoloader --no-interaction

EXPOSE 80
