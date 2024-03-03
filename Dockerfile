ARG BASE_IMAGE=php:8.3-cli-alpine
FROM $BASE_IMAGE as base

WORKDIR /app

RUN docker-php-ext-install pcntl calendar

COPY composer.json .

RUN curl -sS https://getcomposer.org/composer.phar -o composer.phar \
    && php composer.phar update \
    && rm composer.phar

COPY sun2mqtt.php .

ENTRYPOINT ["php", "sun2mqtt.php"]