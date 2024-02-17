FROM composer/composer:latest-bin AS composer
FROM php:8.1-cli-alpine
COPY --from=composer /composer /usr/bin/composer

RUN apk --no-cache add git libzip-dev zip unzip curl

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions xdebug zip curl mbstring