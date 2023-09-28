ARG PHP_VERSION=8

FROM composer:2 AS build

WORKDIR /build

COPY composer.* ./

RUN composer install --no-dev --no-scripts --no-autoloader --ignore-platform-reqs --classmap-authoritative

COPY . .

RUN composer dump-autoload -o

FROM php:${PHP_VERSION}-cli-alpine

WORKDIR /var/www/sapi

RUN set -xe \
    && apk add --no-cache -t .build-deps $PHPIZE_DEPS linux-headers \
    && pecl install -f xdebug-3.2.0 \
    && docker-php-ext-enable xdebug \
    && pecl clear-cache \
    && apk del --purge .build-deps

ARG XDEBUG_PATH=/usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
RUN echo "xdebug.mode = develop,coverage,debug" >> ${XDEBUG_PATH} \
    && echo "xdebug.idekey = docker" >> ${XDEBUG_PATH} \
    && echo "xdebug.client_host = host.docker.internal" >> ${XDEBUG_PATH} \
    && echo "xdebug.client_port = 9003" >> ${XDEBUG_PATH} \
    && echo "xdebug.discover_client_host = 0" >> ${XDEBUG_PATH}

ENV COMPOSER_ALLOW_SUPERUSER=1
COPY --from=build /build /var/www/sapi
COPY --from=build /usr/bin/composer /usr/local/bin/composer
