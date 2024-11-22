FROM docker.io/serversideup/php:8.3-fpm-nginx-alpine

WORKDIR /var/www/html

USER root

# Install the required packages
RUN apk add --no-cache \
    zlib-dev libpng-dev

# Install the required PHP extensions
RUN docker-php-ext-install gd

USER www-data

COPY --chown=www-data:www-data . .
COPY --chmod=755 ./entrypoint.d/ /etc/entrypoint.d

# Install composer and the dependencies
RUN cp .env.example .env \
    && composer install --no-dev --optimize-autoloader \
    && rm -rf .env bootstrap/cache/*.php \
    && chmod -R 777 bootstrap/cache/ storage/logs storage/framework/sessions storage/framework/views storage/framework/cache