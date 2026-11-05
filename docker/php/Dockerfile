FROM php:8.5-fpm-alpine

# PHP extensions required by the application (OPcache is bundled with PHP 8.5).
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions intl pdo_mysql zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/conf.d/app.ini /usr/local/etc/php/conf.d/zz-app.ini

# Run as an unprivileged user whose UID/GID match the host user,
# so files written into the bind-mounted project stay owned by the developer.
ARG UID=1000
ARG GID=1000
RUN if ! getent group "${GID}" > /dev/null; then addgroup -g "${GID}" app; fi \
    && adduser -D -u "${UID}" -G "$(getent group "${GID}" | cut -d: -f1)" -h /home/app app

WORKDIR /var/www/html
USER app

EXPOSE 9000
CMD ["php-fpm"]
