# Development image of the API: PHP 8.5-FPM with the extensions Laravel needs for MySQL.
# The application code is bind-mounted at runtime (see docker-compose.yaml), it is not baked into the image.
FROM composer:2 AS composer

FROM php:8.5-fpm-alpine

RUN apk add --no-cache git unzip \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql \
    && cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini" \
    # The container runs with the host user's UID/GID, so the pool cannot (and need not) switch users.
    && sed -i -e 's/^user = /;user = /' -e 's/^group = /;group = /' /usr/local/etc/php-fpm.d/www.conf

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY conf.d/app.ini "$PHP_INI_DIR/conf.d/zz-app.ini"
COPY --chmod=755 entrypoint.sh /usr/local/bin/app-entrypoint

ENV COMPOSER_HOME=/tmp/composer

WORKDIR /var/www/html

ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]
