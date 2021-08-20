FROM php:7.4-cli-alpine

WORKDIR /app

RUN docker-php-ext-install pcntl calendar

RUN curl -sS https://getcomposer.org/composer.phar -o composer.phar \
    && php composer.phar require php-mqtt/client \
    && rm composer.phar

COPY sun2mqtt.php .

ENTRYPOINT ["php", "sun2mqtt.php"]