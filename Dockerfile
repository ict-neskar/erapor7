FROM docker.io/serversideup/php:8.3-fpm-nginx-alpine

WORKDIR /var/www/html

USER root

# Install the required packages
RUN apk add --no-cache \
    zlib-dev libpng-dev && \
    mkdir -p /var/www/html/bootstrap/cache && \
    mkdir -p /var/www/html/storage/logs && \
    mkdir -p /var/www/html/storage/framework/sessions && \
    mkdir -p /var/www/html/storage/framework/views && \
    mkdir -p /var/www/html/storage/framework/cache && \
    mkdir -p /var/www/html/storage/app/public && \
    chmod -R 777 /var/www/html/bootstrap/cache /var/www/html/storage/logs /var/www/html/storage/framework/sessions /var/www/html/storage/framework/views /var/www/html/storage/framework/cache /var/www/html/storage/app/public && \
    chown -R www-data:www-data /var/www/html/bootstrap/cache /var/www/html/storage/logs /var/www/html/storage/framework/sessions /var/www/html/storage/framework/views /var/www/html/storage/framework/cache /var/www/html/storage/app/public

    # Install the required PHP extensions
RUN docker-php-ext-install gd

USER www-data

COPY --chown=www-data:www-data . .
COPY --chmod=755 ./entrypoint.d/ /etc/entrypoint.d

# Install composer and the dependencies
RUN cp .env.example .env \
    && composer install --no-dev --optimize-autoloader \
    && rm -rf .env bootstrap/cache/*.php 